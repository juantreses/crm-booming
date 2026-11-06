<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Services\LeadCallCountService;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\Services\LeadStatusService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

/**
 * The small services that read or write one field on a lead.
 */
final class LeadFieldServicesTest extends TestCase
{
    use InMemoryEntityManager;

    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    public function testCallCount(): void
    {
        $lead = $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'cCallCount' => 2]));
        $service = new LeadCallCountService($this->inMemoryEntityManager());

        self::assertSame(2, $service->getCount('lead1'));
        self::assertSame(0, $service->getCount('missing'));
        self::assertFalse($service->hasReachedMax('lead1'));
        self::assertTrue($service->hasReachedMax('lead1', 2));

        self::assertSame(3, $service->increment('lead1'));
        self::assertSame(3, $lead->get('cCallCount'));
        self::assertTrue($service->hasReachedMax('lead1'));

        $service->reset('lead1');
        self::assertSame(0, $lead->get('cCallCount'));
        self::assertSame(['Lead:lead1', 'Lead:lead1'], $this->savedEntities());
    }

    public function testCallCountStartsAtZeroWhenEmpty(): void
    {
        $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1']));
        $service = new LeadCallCountService($this->inMemoryEntityManager());

        self::assertSame(0, $service->getCount('lead1'));
        self::assertSame(1, $service->increment('lead1'));
    }

    public function testIncrementUnknownLeadThrows(): void
    {
        $this->expectException(RuntimeException::class);

        (new LeadCallCountService($this->inMemoryEntityManager()))->increment('missing');
    }

    public function testResetUnknownLeadDoesNothing(): void
    {
        (new LeadCallCountService($this->inMemoryEntityManager()))->reset('missing');

        self::assertSame([], $this->savedEntities());
    }

    public function testCoachNoteIsPrependedWithBrusselsTimestampAndSource(): void
    {
        $lead = $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'cNotes' => 'Oude notitie']));
        $service = new LeadNotesService($this->inMemoryEntityManager());

        $service->addCoachNote('lead1', 'Belt terug', 'Telefoon', '2026-07-01 10:00:00');

        self::assertSame("[01/07/2026 12:00] (Telefoon): Belt terug\n\nOude notitie", $lead->get('cNotes'));
        self::assertSame(['Lead:lead1'], $this->savedEntities());
    }

    public function testFirstCoachNote(): void
    {
        $lead = $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1']));

        (new LeadNotesService($this->inMemoryEntityManager()))->addCoachNote('lead1', 'Eerste', 'Afspraak', '2026-01-15 10:00:00');

        self::assertSame('[15/01/2026 11:00] (Afspraak): Eerste', $lead->get('cNotes'));
    }

    public function testCoachNoteForUnknownLeadIsIgnored(): void
    {
        (new LeadNotesService($this->inMemoryEntityManager()))->addCoachNote('missing', 'x', 'Telefoon');

        self::assertSame([], $this->savedEntities());
    }

    public function testFollowUpAction(): void
    {
        $lead = $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1']));
        $service = new LeadFollowUpService($this->inMemoryEntityManager());

        $service->addFollowUpAction('lead1', '2026-07-01 18:30:00');
        self::assertSame('Opnieuw bellen: 01/07/2026 18:30', $lead->get('cFollowUpAction'));

        $service->addFollowUpAction('lead1', '2026-07-01 18:30:00', 'KS twijfel - Opvolging');
        self::assertSame('KS twijfel - Opvolging: 01/07/2026 18:30', $lead->get('cFollowUpAction'));

        $service->setFollowUpActionText('lead1', 'Kickstart boeken');
        self::assertSame('Kickstart boeken', $lead->get('cFollowUpAction'));

        $service->clearFollowUpAction('lead1');
        self::assertSame('', $lead->get('cFollowUpAction'));
    }

    public function testFollowUpActionDoesNotConvertToBrusselsTime(): void
    {
        // Unlike coach notes, the follow-up date is shown as given: a UTC timestamp is not shifted to Brussels.
        $lead = $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1']));

        (new LeadFollowUpService($this->inMemoryEntityManager()))->addFollowUpAction('lead1', '2026-07-01T10:00:00Z');

        self::assertSame('Opnieuw bellen: 01/07/2026 10:00', $lead->get('cFollowUpAction'));
    }

    public function testFollowUpForUnknownLeadIsIgnored(): void
    {
        $service = new LeadFollowUpService($this->inMemoryEntityManager());

        $service->addFollowUpAction('missing', '2026-07-01 18:30:00');
        $service->setFollowUpActionText('missing', 'x');
        $service->clearFollowUpAction('missing');

        self::assertSame([], $this->savedEntities());
    }

    public function testLogEventCreatesEventLinksItAndUpdatesStatus(): void
    {
        $lead = $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'status' => 'Assigned']));

        $related = [];
        $relation = $this->createStub(RDBRelation::class);
        $relation->method('relate')->willReturnCallback(function (Entity $entity) use (&$related): void {
            $related[] = $entity->getId();
        });
        $repository = $this->createStub(RDBRepository::class);
        $repository->method('getRelation')->willReturn($relation);

        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturn($repository);

        $statusService = $this->createMock(LeadStatusService::class);
        $statusService->expects(self::once())->method('updateStatus')->with($lead, LeadEventType::CALL_AGAIN);

        $result = (new LeadEventLogService($entityManager, $statusService))
            ->logEvent('lead1', LeadEventType::CALL_AGAIN, '2026-07-01 12:00:00+02:00', 'Beschrijving');

        self::assertSame(
            ['success' => true, 'eventId' => 'new1', 'eventType' => 'call_again', 'leadStatus' => 'Assigned'],
            $result,
        );

        $event = $this->created[0];
        self::assertSame('CLeadEvent', $event->getEntityType());
        self::assertSame('call_again', $event->get('eventType'));
        self::assertSame('2026-07-01 10:00:00', $event->get('eventDate'));
        self::assertSame('Beschrijving', $event->get('description'));
        self::assertSame(['lead1'], $related);
        self::assertSame(['CLeadEvent:new1', 'CLeadEvent:new1'], $this->savedEntities());
    }

    public function testLogEventWithoutDateUsesNow(): void
    {
        $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1']));
        $entityManager = $this->inMemoryEntityManager();
        $repository = $this->createStub(RDBRepository::class);
        $repository->method('getRelation')->willReturn($this->createStub(RDBRelation::class));
        $entityManager->method('getRDBRepository')->willReturn($repository);

        (new LeadEventLogService($entityManager, $this->createStub(LeadStatusService::class)))
            ->logEvent('lead1', LeadEventType::CALLED);

        self::assertEqualsWithDelta(time(), strtotime($this->created[0]->get('eventDate') . ' UTC'), 5);
    }

    public function testLogEventForUnknownLeadThrows(): void
    {
        $this->expectException(NotFound::class);

        (new LeadEventLogService($this->inMemoryEntityManager(), $this->createStub(LeadStatusService::class)))
            ->logEvent('missing', LeadEventType::CALLED);
    }
}
