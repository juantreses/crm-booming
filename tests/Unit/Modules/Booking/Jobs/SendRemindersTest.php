<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Jobs;

use DateTime;
use DateTimeZone;
use Espo\Modules\Booking\Jobs\SendReminders;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Exception;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class SendRemindersTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var array<string, mixed>|null */
    private ?array $where = null;

    /** @var list<string> */
    private array $sent = [];

    public function testSelectsPlannedMeetingsFromTomorrowUntilTwentyFourHoursFromNow(): void
    {
        $this->runJob([]);

        self::assertNotNull($this->where);
        self::assertSame(['dateStart>=', 'dateStart<=', 'status', 'cReminderSent'], array_keys($this->where));
        self::assertSame((new DateTime('tomorrow', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'), $this->where['dateStart>=']);
        self::assertEqualsWithDelta(time() + 86400, strtotime($this->where['dateStart<='] . ' UTC'), 5);
        self::assertSame('Planned', $this->where['status']);
        self::assertFalse($this->where['cReminderSent']);
    }

    public function testSendsReminderAndMarksMeeting(): void
    {
        $meeting = $this->meeting('m1', ['cMailError' => 'oude fout']);

        $this->runJob([$meeting]);

        self::assertSame(['m1 tpl-m1'], $this->sent);
        self::assertTrue($meeting->get('cReminderSent'));
        self::assertNull($meeting->get('cMailError'));
        self::assertSame(['Meeting:m1'], $this->savedEntities());
    }

    public function testErrorsAreKeptPerMeetingAndOthersStillGetTheirReminder(): void
    {
        $noCalendar = $this->meeting('no-calendar', ['cCalendarId' => null]);
        $noTemplate = $this->meeting('no-template');
        $failing = $this->meeting('failing');
        $ok = $this->meeting('ok');

        $this->runJob([$noCalendar, $noTemplate, $failing, $ok]);

        self::assertSame('Geen kalender gelinkt aan meeting', $noCalendar->get('cMailError'));
        self::assertSame('Geen reminder email gelinkt aan beschikbaarheid of kalender', $noTemplate->get('cMailError'));
        self::assertSame('SMTP down', $failing->get('cMailError'));
        foreach ([$noCalendar, $noTemplate, $failing] as $meeting) {
            self::assertFalse($meeting->get('cReminderSent'));
        }

        self::assertTrue($ok->get('cReminderSent'));
        self::assertSame(['ok tpl-ok'], $this->sent);
        // Failed meetings are saved too, to store the error.
        self::assertSame(['Meeting:no-calendar', 'Meeting:no-template', 'Meeting:failing', 'Meeting:ok'], $this->savedEntities());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function meeting(string $id, array $values = []): Entity
    {
        return FakeEntity::fetched('Meeting', ['id' => $id, 'cCalendarId' => 'cal1', 'cReminderSent' => false, 'status' => 'Planned', ...$values]);
    }

    /**
     * @param list<Entity> $meetings
     */
    private function runJob(array $meetings): void
    {
        $builder = $this->createStub(RDBSelectBuilder::class);
        $builder->method('find')->willReturn(new EntityCollection($meetings));

        $repository = $this->createStub(RDBRepository::class);
        $repository->method('where')->willReturnCallback(function (array $where) use ($builder): RDBSelectBuilder {
            $this->where = $where;

            return $builder;
        });

        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepositoryByClass')->willReturnCallback(
            static fn (string $class) => $class === Meeting::class ? $repository : null,
        );

        $emailService = $this->createStub(BookingEmailService::class);
        $emailService->method('getTemplateIdForMeeting')->willReturnCallback(
            static fn (Entity $meeting, string $type) => $type === 'reminder' && $meeting->getId() !== 'no-template' ? 'tpl-' . $meeting->getId() : null,
        );
        $emailService->method('sendMeetingEmail')->willReturnCallback(function (Entity $meeting, string $templateId): void {
            if ($meeting->getId() === 'failing') {
                throw new Exception('SMTP down');
            }

            $this->sent[] = "{$meeting->getId()} $templateId";
        });

        (new SendReminders($entityManager, $emailService))->run();
    }
}
