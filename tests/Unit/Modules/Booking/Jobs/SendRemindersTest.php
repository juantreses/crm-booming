<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Jobs;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Modules\Booking\Jobs\SendReminders;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testSelectsPlannedMeetingsWithoutReminderInTheWindow(): void
    {
        $this->runJob([]);

        self::assertNotNull($this->where);
        self::assertSame(['dateStart>=', 'dateStart<=', 'status', 'cReminderSent'], array_keys($this->where));
        self::assertSame((new DateTime('tomorrow', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'), $this->where['dateStart>=']);
        self::assertEqualsWithDelta(time() + 25 * 3600, strtotime($this->where['dateStart<='] . ' UTC'), 5);
        self::assertSame('Planned', $this->where['status']);
        self::assertFalse($this->where['cReminderSent']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function windows(): iterable
    {
        yield 'morning' => ['2026-01-19 08:00:00', '2026-01-20 00:00:00', '2026-01-20 09:00:00'];
        yield 'last run of the day reaches the end of tomorrow' => ['2026-01-19 23:00:00', '2026-01-20 00:00:00', '2026-01-21 00:00:00'];
        yield 'first run of the day' => ['2026-01-20 00:00:00', '2026-01-21 00:00:00', '2026-01-21 01:00:00'];
        // 01:30 in Brussels (summer) is 23:30 UTC the day before.
        yield 'given in another timezone' => ['2026-07-01 01:30:00 Europe/Brussels', '2026-07-01 00:00:00', '2026-07-02 00:30:00'];
    }

    #[DataProvider('windows')]
    public function testWindow(string $now, string $from, string $until): void
    {
        self::assertSame([$from, $until], $this->job()->getWindow(new DateTimeImmutable($now)));
    }

    public function testEveryMeetingOfTomorrowGetsOneTimelyReminderFromTheHourlyRuns(): void
    {
        $job = $this->job();
        $utc = new DateTimeZone('UTC');
        $runs = [];
        for ($run = new DateTimeImmutable('2026-01-18 00:00:00', $utc); $run < new DateTimeImmutable('2026-01-22 00:00:00', $utc); $run = $run->modify('+1 hour')) {
            $runs[] = [$run, ...$job->getWindow($run)];
        }

        // Every 5 minutes on 20 January, including the last hour before midnight.
        for ($meeting = new DateTimeImmutable('2026-01-20 00:00:00', $utc); $meeting < new DateTimeImmutable('2026-01-21 00:00:00', $utc); $meeting = $meeting->modify('+5 minutes')) {
            $start = $meeting->format('Y-m-d H:i:s');
            $first = null;

            foreach ($runs as [$run, $from, $until]) {
                if ($start >= $from && $start <= $until) {
                    $first = $run;
                    break;
                }
            }

            self::assertNotNull($first, "No reminder for a meeting at $start");
            $hoursBefore = ($meeting->getTimestamp() - $first->getTimestamp()) / 3600;
            self::assertGreaterThanOrEqual(24, $hoursBefore, "Reminder for $start too late");
            self::assertLessThanOrEqual(25, $hoursBefore, "Reminder for $start too early");
        }
    }

    public function testMeetingsOfTodayAreNeverInTheWindow(): void
    {
        $job = $this->job();

        foreach (['00:00', '09:00', '22:00', '23:00'] as $time) {
            [$from] = $job->getWindow(new DateTimeImmutable("2026-01-20 $time:00", new DateTimeZone('UTC')));

            self::assertSame('2026-01-21 00:00:00', $from);
        }
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

    private function job(): SendReminders
    {
        return new SendReminders($this->inMemoryEntityManager(), $this->createStub(BookingEmailService::class));
    }
}
