<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Services;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Services\LeadCallCountService;
use Espo\Modules\LeadManager\Services\LeadStatusService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\RDBRelation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class LeadStatusServiceTest extends TestCase
{
    use InMemoryEntityManager;

    /**
     * Event type => [status, cStage, cReasonLost or 'unchanged'].
     *
     * @return iterable<string, array{LeadEventType, string, ?string, ?string}>
     */
    public static function statusMap(): iterable
    {
        $unchanged = 'unchanged';

        yield 'assigned' => [LeadEventType::ASSIGNED, 'Assigned', 'to_call', $unchanged];
        yield 'reset clears reason lost' => [LeadEventType::RESET, 'Assigned', 'to_call', null];
        yield 'call again' => [LeadEventType::CALL_AGAIN, 'Assigned', 'follow_up', $unchanged];
        yield 'wrong number' => [LeadEventType::WRONG_NUMBER, 'Dead', null, 'wrong_number'];
        yield 'not interested' => [LeadEventType::NOT_INTERESTED, 'Dead', null, 'not_interested'];
        yield 'message to be sent' => [LeadEventType::MESSAGE_TO_BE_SENT, 'Assigned', 'message_to_be_sent', $unchanged];
        yield 'message sent' => [LeadEventType::MESSAGE_SENT, 'Assigned', 'message_sent', $unchanged];
        yield 'book intro' => [LeadEventType::BOOK_INTRO, 'Assigned', 'intro_scheduled', $unchanged];
        yield 'intro no show' => [LeadEventType::INTRO_NO_SHOW, 'Assigned', 'follow_up', $unchanged];
        yield 'kickstart booked' => [LeadEventType::KICKSTART_BOOKED, 'Assigned', 'ks_planned', $unchanged];
        yield 'became client' => [LeadEventType::BECAME_CLIENT, 'became_client', 'became_client', $unchanged];
        yield 'became coach' => [LeadEventType::BECAME_COACH, 'Converted', null, $unchanged];
        yield 'not converted' => [LeadEventType::NOT_CONVERTED, 'Dead', null, 'not_converted'];
        yield 'cancelled stop' => [LeadEventType::CANCELLED_STOP, 'Dead', null, 'appointment_cancelled'];
        yield 'still thinking' => [LeadEventType::STILL_THINKING, 'Assigned', 'ks_doubt', $unchanged];
        yield 'no show' => [LeadEventType::NO_SHOW, 'Assigned', 'follow_up', $unchanged];
        yield 'kickstart no show' => [LeadEventType::KICKSTART_NO_SHOW, 'Assigned', 'follow_up', $unchanged];
        yield 'royalty booked' => [LeadEventType::ROYALTY_BOOKED, 'Assigned', 'ri_planned', $unchanged];
        yield 'royalty still thinking' => [LeadEventType::ROYALTY_STILL_THINKING, 'Assigned', 'ri_doubt', $unchanged];
    }

    #[DataProvider('statusMap')]
    public function testUpdateStatus(LeadEventType $eventType, string $status, ?string $stage, ?string $reasonLost): void
    {
        $lead = $this->lead();

        $this->service()->updateStatus($lead, $eventType);

        self::assertSame($status, $lead->get('status'));
        self::assertSame($stage, $lead->get('cStage'));
        self::assertSame($reasonLost === 'unchanged' ? 'previous' : $reasonLost, $lead->get('cReasonLost'));
        self::assertSame(['Lead:lead1'], $this->savedEntities());
    }

    /**
     * @return iterable<string, array{LeadEventType}>
     */
    public static function eventsWithoutStatusChange(): iterable
    {
        $mapped = array_map(static fn (array $row) => $row[0], iterator_to_array(self::statusMap()));

        foreach (LeadEventType::cases() as $type) {
            if (!in_array($type, $mapped, true) && $type !== LeadEventType::NO_ANSWER) {
                yield $type->value => [$type];
            }
        }
    }

    #[DataProvider('eventsWithoutStatusChange')]
    public function testOtherEventsLeaveTheLeadUntouched(LeadEventType $eventType): void
    {
        $lead = $this->lead();

        $this->service()->updateStatus($lead, $eventType);

        self::assertSame('New', $lead->get('status'));
        self::assertSame('to_call', $lead->get('cStage'));
        self::assertSame([], $this->savedEntities());
    }

    public function testNoAnswerBelowMaxAttemptsGoesToFollowUp(): void
    {
        $lead = $this->lead();

        $this->service(callCount: 2, team: FakeEntity::create('CTeam', ['id' => 't1', 'maxCallAttempts' => 3]))
            ->updateStatus($lead, LeadEventType::NO_ANSWER);

        self::assertSame('Assigned', $lead->get('status'));
        self::assertSame('follow_up', $lead->get('cStage'));
        self::assertSame(['Lead:lead1'], $this->savedEntities());
    }

    public function testNoAnswerAtMaxAttemptsGoesToMessageToBeSent(): void
    {
        $lead = $this->lead();

        $this->service(callCount: 5, team: FakeEntity::create('CTeam', ['id' => 't1', 'maxCallAttempts' => 5]))
            ->updateStatus($lead, LeadEventType::NO_ANSWER);

        self::assertSame('message_to_be_sent', $lead->get('cStage'));
    }

    public function testNoAnswerDefaultsToThreeAttemptsWithoutTeam(): void
    {
        $belowMax = $this->lead();
        $this->service(callCount: 2)->updateStatus($belowMax, LeadEventType::NO_ANSWER);
        self::assertSame('follow_up', $belowMax->get('cStage'));

        $atMax = $this->lead();
        $this->service(callCount: 3)->updateStatus($atMax, LeadEventType::NO_ANSWER);
        self::assertSame('message_to_be_sent', $atMax->get('cStage'));
    }

    public function testNoAnswerDefaultsToThreeAttemptsWhenTeamHasNoMaximum(): void
    {
        $lead = $this->lead();

        $this->service(callCount: 3, team: FakeEntity::create('CTeam', ['id' => 't1']))
            ->updateStatus($lead, LeadEventType::NO_ANSWER);

        self::assertSame('message_to_be_sent', $lead->get('cStage'));
    }

    public function testGetStatusForEvent(): void
    {
        $service = $this->service();

        self::assertSame(
            ['status' => 'Dead', 'cStage' => null, 'cReasonLost' => 'wrong_number'],
            $service->getStatusForEvent(LeadEventType::WRONG_NUMBER),
        );
        self::assertNull($service->getStatusForEvent(LeadEventType::CALLED));
        self::assertNull($service->getStatusForEvent(LeadEventType::NO_ANSWER));
    }

    public function testIsDeadEvent(): void
    {
        $service = $this->service();

        foreach ([LeadEventType::WRONG_NUMBER, LeadEventType::NOT_INTERESTED, LeadEventType::NOT_CONVERTED, LeadEventType::CANCELLED_STOP] as $type) {
            self::assertTrue($service->isDeadEvent($type), $type->value);
        }

        self::assertFalse($service->isDeadEvent(LeadEventType::CALL_AGAIN));
        self::assertFalse($service->isDeadEvent(LeadEventType::CALLED));
    }

    public function testIsConversionEvent(): void
    {
        $service = $this->service();

        self::assertTrue($service->isConversionEvent(LeadEventType::BECAME_COACH));
        // BECAME_CLIENT has its own status, not 'Converted'.
        self::assertFalse($service->isConversionEvent(LeadEventType::BECAME_CLIENT));
        self::assertFalse($service->isConversionEvent(LeadEventType::CALLED));
    }

    private function lead(): Entity
    {
        return FakeEntity::create('Lead', ['id' => 'lead1', 'status' => 'New', 'cStage' => 'to_call', 'cReasonLost' => 'previous']);
    }

    private function service(int $callCount = 0, ?Entity $team = null): LeadStatusService
    {
        $relation = $this->createStub(RDBRelation::class);
        $relation->method('findOne')->willReturn($team);

        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRelation')->willReturn($relation);

        $callCountService = $this->createStub(LeadCallCountService::class);
        $callCountService->method('getCount')->willReturn($callCount);

        return new LeadStatusService($entityManager, $callCountService);
    }
}
