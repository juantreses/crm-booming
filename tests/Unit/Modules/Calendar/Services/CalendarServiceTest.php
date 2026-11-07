<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Calendar\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Calendar\Services\CalendarService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use Tests\Support\RecordingQueries;

final class CalendarServiceTest extends TestCase
{
    use InMemoryEntityManager;
    use RecordingQueries;

    /** A date far enough ahead that the minimum lead time never applies. */
    private const DATE = '2030-01-15';

    private Entity $calendar;

    /** @var array<string, list<Entity>> date => availabilities (key '*' for every date) */
    private array $availabilities = [];

    /** @var array<string, array<string, int>> location ID ('' for none) => start time => booked seats */
    private array $bookings = [];

    /** @var array<string, array<string, array<string, int>>> date => bookings like $bookings, overrides $bookings */
    private array $bookingsPerDate = [];

    /** @var list<array<string, mixed>> */
    private array $blockingAvailabilities = [];

    /** @var list<array<string, mixed>> */
    private array $blockingMeetings = [];

    /** @var list<array{string, ?string, ?string, ?string}> date, locationId, teamId, variant per availability lookup */
    private array $availabilityLookups = [];

    /** @var array<string, string> identifier => id for SlugService */
    private array $slugs = [
        'kickstart' => 'cal1',
        'cal1' => 'cal1',
        'gent' => 'loc-gent',
        'loc-gent' => 'loc-gent',
        'jan' => 'user-jan',
    ];

    protected function setUp(): void
    {
        $this->calendar = FakeEntity::create('CCalendar', [
            'id' => 'cal1',
            'name' => 'Kickstart',
            'description' => 'Kennismaking',
            'isActive' => true,
            'duration' => 60,
            'bufferTime' => 0,
            'seatsPerMeeting' => 2,
            'minLeadTime' => 0,
            'maxBookingRange' => null,
        ]);
        $this->availabilities['*'] = [$this->availability('av1', '18:00', '20:00')];
    }

    public function testSlotsForAnAvailability(): void
    {
        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        self::assertSame([
            ['availabilityId' => 'av1', 'start' => '18:00', 'end' => '19:00', 'isBookable' => true, 'availableSeats' => 2, 'reason' => ''],
            ['availabilityId' => 'av1', 'start' => '19:00', 'end' => '20:00', 'isBookable' => true, 'availableSeats' => 2, 'reason' => ''],
        ], $slots);
        self::assertSame([[self::DATE, null, null, null]], $this->availabilityLookups);
    }

    public function testBufferBetweenSlots(): void
    {
        $this->calendar->set(['duration' => 45, 'bufferTime' => 15]);
        $this->availabilities['*'] = [$this->availability('av1', '18:00', '20:00')];

        self::assertSame(['18:00-18:45', '19:00-19:45'], $this->times($this->service()->getAvailableSlots('kickstart', self::DATE)));
    }

    public function testSlotsEndWithinTheAvailability(): void
    {
        $this->availabilities['*'] = [$this->availability('av1', '18:00', '19:30')];

        self::assertSame(['18:00-19:00'], $this->times($this->service()->getAvailableSlots('kickstart', self::DATE)));

        $this->calendar->set(['duration' => 45, 'bufferTime' => 15]);
        $this->availabilities['*'] = [$this->availability('av1', '18:00', '19:45')];

        // The last slot may end exactly at the end of the availability.
        self::assertSame(['18:00-18:45', '19:00-19:45'], $this->times($this->service()->getAvailableSlots('kickstart', self::DATE)));
    }

    public function testBookedSeats(): void
    {
        $this->bookings = ['' => ['18:00' => 2, '19:00' => 1]];

        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        self::assertSame([false, 0, 'Vol'], [$slots[0]['isBookable'], $slots[0]['availableSeats'], $slots[0]['reason']]);
        self::assertSame([true, 1, ''], [$slots[1]['isBookable'], $slots[1]['availableSeats'], $slots[1]['reason']]);
    }

    public function testBlockedSlotsAreLeftOut(): void
    {
        $this->blockingAvailabilities = [['startTime' => '18:30', 'endTime' => '19:00', '_bufferTime' => 0]];

        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        // Blocked slots are not returned at all, so the 'Geblokkeerd' reason is never shown.
        self::assertSame(['19:00-20:00'], $this->times($slots));
    }

    public function testBlockingBufferExtendsTheBlock(): void
    {
        $this->blockingMeetings = [['startTime' => '17:00', 'endTime' => '17:45', '_bufferTime' => 30, '_isMeeting' => true]];

        self::assertSame(['19:00-20:00'], $this->times($this->service()->getAvailableSlots('kickstart', self::DATE)));

        $this->blockingMeetings = [['startTime' => '17:00', 'endTime' => '17:45', '_bufferTime' => 15, '_isMeeting' => true]];

        self::assertSame(['18:00-19:00', '19:00-20:00'], $this->times($this->service()->getAvailableSlots('kickstart', self::DATE)));
    }

    public function testBlockWithoutTimesIsIgnored(): void
    {
        $this->blockingAvailabilities = [['startTime' => null, 'endTime' => '23:00'], ['startTime' => '00:00']];

        self::assertCount(2, $this->service()->getAvailableSlots('kickstart', self::DATE));
    }

    public function testLocationAndVariantDetails(): void
    {
        $this->availabilities['*'] = [
            $this->availability('av1', '18:00', '19:00', 'loc-gent', ['publicSlug' => 'avond', 'name' => 'Avond', 'description' => 'Na het werk']),
        ];

        $slot = $this->service()->getAvailableSlots('kickstart', self::DATE)[0];

        self::assertSame('loc-gent', $slot['locationId']);
        self::assertSame('Gent', $slot['locationName']);
        self::assertSame('Straat 1', $slot['locationAddressStreet']);
        self::assertSame('9000', $slot['locationAddressPostalCode']);
        self::assertSame('avond', $slot['variantSlug']);
        self::assertSame('Avond', $slot['variantName']);
        self::assertSame('Na het werk', $slot['variantDescription']);
    }

    public function testEveryLocationAndVariantKeepsItsSlots(): void
    {
        $this->availabilities['*'] = [
            $this->availability('av-gent', '19:00', '20:00', 'loc-gent'),
            $this->availability('av-brugge', '18:00', '20:00', 'loc-brugge'),
            $this->availability('av-gent-avond', '19:00', '20:00', 'loc-gent', ['publicSlug' => 'avond']),
        ];

        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        self::assertSame(
            ['18:00 av-brugge', '19:00 av-gent', '19:00 av-brugge', '19:00 av-gent-avond'],
            array_map(static fn (array $s) => "{$s['start']} {$s['availabilityId']}", $slots),
        );
    }

    public function testOverlappingAvailabilitiesOfTheSameLocationGiveOneSlot(): void
    {
        $this->availabilities['*'] = [
            $this->availability('av-weekly', '18:00', '20:00', 'loc-gent'),
            $this->availability('av-extra', '19:00', '21:00', 'loc-gent'),
        ];

        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        self::assertSame(
            ['18:00 av-weekly', '19:00 av-weekly', '20:00 av-extra'],
            array_map(static fn (array $s) => "{$s['start']} {$s['availabilityId']}", $slots),
        );
    }

    public function testSeatsAreCountedPerLocation(): void
    {
        $this->availabilities['*'] = [
            $this->availability('av-gent', '19:00', '20:00', 'loc-gent'),
            $this->availability('av-brugge', '19:00', '20:00', 'loc-brugge'),
        ];
        $this->bookings = ['loc-gent' => ['19:00' => 2], 'loc-brugge' => ['19:00' => 1]];

        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        self::assertSame(['av-gent', 'av-brugge'], array_column($slots, 'availabilityId'));
        self::assertSame([0, 1], array_column($slots, 'availableSeats'));
        self::assertSame(['Vol', ''], array_column($slots, 'reason'));
    }

    public function testBookingWithoutLocationTakesASeatEverywhere(): void
    {
        $this->availabilities['*'] = [
            $this->availability('av-gent', '19:00', '20:00', 'loc-gent'),
            $this->availability('av-brugge', '19:00', '20:00', 'loc-brugge'),
        ];
        $this->bookings = ['' => ['19:00' => 1], 'loc-gent' => ['19:00' => 1]];

        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        self::assertSame([0, 1], array_column($slots, 'availableSeats'));
    }

    public function testSlotWithoutLocationCountsAllBookings(): void
    {
        $this->bookings = ['' => ['18:00' => 1], 'loc-gent' => ['18:00' => 1]];

        $slots = $this->service()->getAvailableSlots('kickstart', self::DATE);

        self::assertSame([0, 2], array_column($slots, 'availableSeats'));
    }

    public function testFiltersArePassedToTheRepository(): void
    {
        $this->queryResults['CTeam'] = [FakeEntity::create('CTeam', ['id' => 'team-jan'])];

        $this->service()->getAvailableSlots('kickstart', self::DATE, 'gent', 'jan', 'avond');

        self::assertSame([[self::DATE, 'loc-gent', 'team-jan', 'avond']], $this->availabilityLookups);
        self::assertSame(['CTeam where {"assignedUserId":"user-jan"} findOne'], $this->queries);
    }

    public function testUnknownCoachOrCoachWithoutTeamMeansNoTeamFilter(): void
    {
        $service = $this->service();

        $service->getAvailableSlots('kickstart', self::DATE, null, 'onbekend');
        $service->getAvailableSlots('kickstart', self::DATE, null, 'jan');

        self::assertSame([null, null], array_column($this->availabilityLookups, 2));
        self::assertCount(1, $this->queries);
    }

    public function testNoAvailabilitiesMeansNoSlots(): void
    {
        $this->availabilities = [];

        self::assertSame([], $this->service()->getAvailableSlots('kickstart', self::DATE));
    }

    public function testUnknownCalendar(): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Calendar identifier not found: onbekend');

        $this->service()->getAvailableSlots('onbekend', self::DATE);
    }

    public function testInactiveCalendar(): void
    {
        $this->calendar->set('isActive', false);

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage('Calendar not active');

        $this->service()->getAvailableSlots('kickstart', self::DATE);
    }

    public function testMissingCalendar(): void
    {
        $this->slugs['weg'] = 'cal-weg';

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage('Calendar not found');

        $this->service()->getAvailableSlots('weg', self::DATE);
    }

    public function testMinimumLeadTimeInBrusselsTime(): void
    {
        // Put the first bookable moment between 12:00 and 13:00 Brussels time on a day ten days from now.
        $brussels = new DateTimeZone('Europe/Brussels');
        $date = (new DateTimeImmutable('today +10 days', $brussels))->format('Y-m-d');
        $target = new DateTimeImmutable("$date 12:00:00", $brussels);
        $this->calendar->set('minLeadTime', intdiv($target->getTimestamp() - time(), 3600) + 1);
        $this->availabilities['*'] = [$this->availability('av1', '11:00', '14:00')];

        $slots = $this->service()->getAvailableSlots('kickstart', $date);

        self::assertSame(
            ['11:00 Te kort op voorhand', '12:00 Te kort op voorhand', '13:00 '],
            array_map(static fn (array $s) => "{$s['start']} {$s['reason']}", $slots),
        );
        self::assertSame([false, false, true], array_column($slots, 'isBookable'));
    }

    public function testSlotThatStartedThisHourIsNotBookable(): void
    {
        // E.g. at 10:20 the 10:00 slot has started. Read as UTC it would still be in the future.
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels'));
        if ((int) $now->format('i') === 0) {
            self::markTestSkipped('The slot of this hour starts right now.');
        }

        $this->calendar->set('duration', 60);
        $this->availabilities['*'] = [$this->availability('av1', $now->format('H:00'), $now->modify('+1 hour')->format('H:00'))];

        $slots = $this->service()->getAvailableSlots('kickstart', $now->format('Y-m-d'));

        if ($now->format('H') === '23') {
            // The availability would end at 00:00, before it starts: no slots.
            self::assertSame([], $slots);

            return;
        }

        self::assertSame([false, 'Te kort op voorhand'], [$slots[0]['isBookable'], $slots[0]['reason']]);
    }

    public function testBookableCalendars(): void
    {
        $repository = $this->repository();
        $repository->method('getBookableCalendars')->willReturn(new EntityCollection([
            FakeEntity::create('CCalendar', ['id' => 'cal1', 'name' => 'Kickstart', 'type' => 'kickstart', 'slug' => 'ks']),
        ]));

        self::assertSame(
            [['id' => 'cal1', 'name' => 'Kickstart', 'type' => 'kickstart', 'identifier' => 'ks']],
            $this->service($repository)->getBookableCalendars(),
        );
    }

    public function testCalendarByType(): void
    {
        $repository = $this->repository();
        $repository->method('findActiveCalendarByType')->willReturnCallback(
            fn (string $type) => $type === 'kickstart' ? $this->calendar : null,
        );
        $service = $this->service($repository);

        self::assertSame(['id' => 'cal1', 'name' => 'Kickstart'], $service->getCalendarByType('kickstart'));

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage("Geen actieve agenda gevonden voor type 'workout'.");

        $service->getCalendarByType('workout');
    }

    public function testSettings(): void
    {
        self::assertSame(
            ['name' => 'Kickstart', 'description' => 'Kennismaking', 'duration' => 60, 'maxBookingRange' => 30],
            $this->service()->getSettings('kickstart'),
        );

        $this->calendar->set('maxBookingRange', 90);
        self::assertSame(90, $this->service()->getSettings('kickstart')['maxBookingRange']);
    }

    public function testUpcomingSlotsPerDayWithinTheRange(): void
    {
        $utc = new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $utc);
        $this->availabilities = [];
        foreach ([1, 3, 13, 14] as $days) {
            $this->availabilities[$today->modify("+$days days")->format('Y-m-d')] = [$this->availability('av1', '18:00', '19:00')];
        }

        $slots = $this->service()->getUpcomingSlots('kickstart', 'jan');

        // Default range: 14 days, starting today (so up to day 13).
        self::assertSame(
            array_map(static fn (int $d) => $today->modify("+$d days")->format('Y-m-d'), [1, 3, 13]),
            array_keys($slots),
        );
        self::assertCount(14, $this->availabilityLookups);
        // The coach is passed on for every day.
        self::assertCount(14, array_filter($this->queries, static fn (string $q) => str_starts_with($q, 'CTeam')));
    }

    public function testUpcomingSlotsRangeIsAtMostSixtyDays(): void
    {
        $this->calendar->set('maxBookingRange', 365);
        $this->availabilities = [];

        $this->service()->getUpcomingSlots('kickstart');

        self::assertCount(60, $this->availabilityLookups);
    }

    public function testUpcomingSlotsSkipsDaysThatFail(): void
    {
        $this->calendar->set('maxBookingRange', 3);
        $failingDay = (new DateTimeImmutable('today +1 day', new DateTimeZone('UTC')))->format('Y-m-d');
        $logFile = (string) tempnam(sys_get_temp_dir(), 'calendar-test');
        $originalLog = ini_set('error_log', $logFile);

        try {
            $slots = $this->service($this->repository(failOn: $failingDay))->getUpcomingSlots('kickstart');
        } finally {
            ini_set('error_log', (string) $originalLog);
        }

        self::assertCount(2, $slots);
        self::assertArrayNotHasKey($failingDay, $slots);
        self::assertStringContainsString("Calendar slots error for $failingDay: DB error", (string) file_get_contents($logFile));
        unlink($logFile);
    }

    public function testUpcomingSlotsUnknownCalendar(): void
    {
        $this->slugs['weg'] = 'cal-weg';
        $service = $this->service();

        try {
            $service->getUpcomingSlots('onbekend');
            self::fail('Expected BadRequest');
        } catch (BadRequest $e) {
            self::assertSame('Calendar identifier not found: onbekend', $e->getMessage());
        }

        $this->expectException(NotFound::class);

        $service->getUpcomingSlots('weg');
    }

    public function testMonthAvailabilityListsDaysWithABookableSlotWithinTheRange(): void
    {
        $utc = new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $utc);
        $month = $today->modify('first day of next month');
        $this->calendar->set('maxBookingRange', 45);
        $fullDay = $month->modify('+4 days')->format('Y-m-d');
        $this->availabilities = ['*' => [$this->availability('av1', '18:00', '19:00')]];
        $this->bookingsPerDate = [$fullDay => ['' => ['18:00' => 2]]];

        $dates = $this->service()->getMonthAvailability('kickstart', (int) $month->format('Y'), (int) $month->format('m'), 'gent');

        $expected = [];
        for ($day = $month; $day->format('m') === $month->format('m'); $day = $day->modify('+1 day')) {
            if ($day <= $today->modify('+45 days') && $day->format('Y-m-d') !== $fullDay) {
                $expected[] = $day->format('Y-m-d');
            }
        }

        self::assertSame($expected, $dates);
        self::assertSame(['loc-gent'], array_values(array_unique(array_column($this->availabilityLookups, 1))));
    }

    public function testMonthAvailabilitySkipsPastDays(): void
    {
        $lastMonth = (new DateTimeImmutable('first day of last month', new DateTimeZone('UTC')));

        $dates = $this->service()->getMonthAvailability('kickstart', (int) $lastMonth->format('Y'), (int) $lastMonth->format('m'));

        self::assertSame([], $dates);
        self::assertSame([], $this->availabilityLookups);
    }

    public function testMonthAvailabilityOfUnknownCalendar(): void
    {
        $this->slugs['weg'] = 'cal-weg';

        self::assertSame([], $this->service()->getMonthAvailability('weg', 2030, 1));

        $this->expectException(BadRequest::class);

        $this->service()->getMonthAvailability('onbekend', 2030, 1);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function availability(string $id, string $start, string $end, ?string $locationId = null, array $values = []): Entity
    {
        return FakeEntity::create('CAvailability', [
            'id' => $id,
            'startTime' => $start,
            'endTime' => $end,
            'locationId' => $locationId,
            'publicSlug' => null,
            ...$values,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return list<string>
     */
    private function times(array $slots): array
    {
        return array_map(static fn (array $s) => "{$s['start']}-{$s['end']}", $slots);
    }

    private function repository(?string $failOn = null): CalendarRepository&Stub
    {
        $repository = $this->createStub(CalendarRepository::class);
        $repository->method('findCalendarById')->willReturnCallback(fn (string $id) => $id === 'cal1' ? $this->calendar : null);
        $repository->method('getAvailabilitiesForDate')->willReturnCallback(
            function (string $calendarId, string $date, ?string $locationId = null, ?string $teamId = null, ?string $variant = null) use ($failOn): EntityCollection {
                if ($date === $failOn) {
                    throw new RuntimeException('DB error');
                }

                $this->availabilityLookups[] = [$date, $locationId, $teamId, $variant];

                return new EntityCollection($this->availabilities[$date] ?? $this->availabilities['*'] ?? []);
            },
        );
        $repository->method('getLocationsByIds')->willReturnCallback(static fn (array $ids) => array_intersect_key([
            'loc-gent' => [
                'id' => 'loc-gent', 'name' => 'Gent', 'addressStreet' => 'Straat 1', 'addressCity' => 'Gent',
                'addressState' => null, 'addressCountry' => 'BE', 'addressPostalCode' => '9000',
            ],
            'loc-brugge' => [
                'id' => 'loc-brugge', 'name' => 'Brugge', 'addressStreet' => null, 'addressCity' => 'Brugge',
                'addressState' => null, 'addressCountry' => 'BE', 'addressPostalCode' => '8000',
            ],
        ], array_flip($ids)));
        $repository->method('getBlockedByCalendarIds')->willReturn(['blocker']);
        $repository->method('getBlockingCalendarAvailabilities')->willReturnCallback(fn () => $this->blockingAvailabilities);
        $repository->method('getBlockingCalendarMeetings')->willReturnCallback(fn () => $this->blockingMeetings);
        $repository->method('getBookingsForDate')->willReturnCallback(
            fn (string $calendarId, string $date) => $this->bookingsPerDate[$date] ?? $this->bookings,
        );

        return $repository;
    }

    private function service(?CalendarRepository $repository = null): CalendarService
    {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->recordingRepository($type));

        $slugService = $this->createStub(SlugService::class);
        $slugService->method('resolve')->willReturnCallback(
            fn (string $type, ?string $identifier) => $identifier === null ? null : ($this->slugs[$identifier] ?? null),
        );

        return new CalendarService($entityManager, $repository ?? $this->repository(), $slugService);
    }
}
