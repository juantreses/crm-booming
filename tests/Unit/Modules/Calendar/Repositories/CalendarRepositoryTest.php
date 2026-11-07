<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Calendar\Repositories;

use Espo\Modules\Calendar\Repositories\CalendarRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use Tests\Support\RecordingQueries;

final class CalendarRepositoryTest extends TestCase
{
    use InMemoryEntityManager;
    use RecordingQueries;

    public function testFindCalendarById(): void
    {
        $calendar = $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'cal1']));

        self::assertSame($calendar, $this->repository()->findCalendarById('cal1'));
        self::assertNull($this->repository()->findCalendarById('gone'));
    }

    public function testBookableAndActiveCalendarQueries(): void
    {
        $repository = $this->repository();

        $repository->getBookableCalendars();
        $repository->findActiveCalendarByType('workout');
        $repository->getActiveCalendars();

        self::assertSame([
            'CCalendar where {"isActive":true,"isDirectBookable":true,"deleted":false} find',
            'CCalendar where {"isActive":true,"type":"workout","deleted":false} order createdAt ASC findOne',
            'CCalendar where {"isActive":true,"hasPublicLink":true} find',
        ], $this->queries);
    }

    public function testAvailabilitiesForDateUseWeekdayAndFilters(): void
    {
        $repository = $this->repository();

        // 2026-01-20 is a Tuesday (2).
        $repository->getAvailabilitiesForDate('cal1', '2026-01-20');
        $repository->getAvailabilitiesForDate('cal1', '2026-01-25', 'loc1', null, 'avond');
        $repository->getAvailabilitiesForDate('cal1', '2026-01-20', null, 'team1');

        $base = '"calendarId":"cal1","0":{"OR":[{"type":"specific","date":"%s"},{"type":"recurrent","daysOfWeek*":"%%%d%%"}]}';
        self::assertSame([
            'CAvailability where {' . sprintf($base, '2026-01-20', 2) . '} find',
            'CAvailability where {' . sprintf($base, '2026-01-25', 0) . ',"locationId":"loc1","publicSlug":"avond"} find',
            'CAvailability leftJoin cTeams teamJoin where {' . sprintf($base, '2026-01-20', 2) . '}'
                . ' where {"OR":[{"teamJoin.id":"team1"},{"teamJoin.id":null}]} distinct find',
        ], $this->queries);
    }

    public function testBlockedByCalendarIds(): void
    {
        $this->queryResults['CCalendar.blockedByCalendars'] = [
            FakeEntity::create('CCalendar', ['id' => 'ks']),
            FakeEntity::create('CCalendar', ['id' => 'ri']),
        ];

        $ids = $this->repository()->getBlockedByCalendarIds(FakeEntity::create('CCalendar', ['id' => 'spark']));

        self::assertSame(['ks', 'ri'], $ids);
        self::assertSame(['CCalendar.blockedByCalendars of spark find'], $this->queries);
    }

    public function testBlockingAvailabilitiesCarryTheBufferOfTheirCalendar(): void
    {
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'ks', 'bufferTime' => 15]));
        $this->queryResults['CAvailability'] = [
            FakeEntity::create('CAvailability', ['id' => 'a1', 'calendarId' => 'ks', 'startTime' => '18:00', 'endTime' => '20:00']),
            FakeEntity::create('CAvailability', ['id' => 'a2', 'calendarId' => 'gone', 'startTime' => '09:00', 'endTime' => '10:00']),
            FakeEntity::create('CAvailability', ['id' => 'a3', 'calendarId' => null, 'startTime' => '11:00', 'endTime' => '12:00']),
        ];

        $result = $this->repository()->getBlockingCalendarAvailabilities(['ks', 'gone'], '2026-01-20');

        self::assertSame([15, 0, 0], array_column($result, '_bufferTime'));
        self::assertSame(['18:00', '09:00', '11:00'], array_column($result, 'startTime'));
        self::assertStringStartsWith('CAvailability where {"calendarId":["ks","gone"],"OR":', $this->queries[0]);
    }

    public function testNoBlockingCalendarsMeansNoQueries(): void
    {
        self::assertSame([], $this->repository()->getBlockingCalendarAvailabilities([], '2026-01-20'));
        self::assertSame([], $this->repository()->getBlockingCalendarMeetings([], '2026-01-20'));
        self::assertSame([], $this->queries);
    }

    public function testBlockingMeetingsInBrusselsTime(): void
    {
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'ks', 'bufferTime' => 30]));
        $this->queryResults['Meeting'] = [
            FakeEntity::create('Meeting', ['id' => 'm1', 'cCalendarId' => 'ks', 'dateStart' => '2026-01-20 17:00:00', 'dateEnd' => '2026-01-20 18:30:00']),
            FakeEntity::create('Meeting', ['id' => 'm2', 'cCalendarId' => null, 'dateStart' => '2026-07-01 08:00:00', 'dateEnd' => '2026-07-01 09:00:00']),
        ];

        $result = $this->repository()->getBlockingCalendarMeetings(['ks'], '2026-01-20');

        self::assertSame([
            ['startTime' => '18:00', 'endTime' => '19:30', '_bufferTime' => 30, '_isMeeting' => true],
            ['startTime' => '10:00', 'endTime' => '11:00', '_bufferTime' => 0, '_isMeeting' => true],
        ], $result);
        self::assertSame([
            'Meeting where {"cCalendarId":["ks"],"dateStart>=":"2026-01-20 00:00:00","dateStart<=":"2026-01-20 23:59:59",'
                . '"status!=":["Cancelled","Tentative"]} find',
        ], $this->queries);
    }

    public function testBookingsAreCountedPerLocationAndBrusselsStartTime(): void
    {
        $this->queryResults['Meeting'] = [
            FakeEntity::create('Meeting', ['id' => 'm1', 'dateStart' => '2026-01-20 17:00:00', 'cLocationId' => 'loc-gent']),
            FakeEntity::create('Meeting', ['id' => 'm2', 'dateStart' => '2026-01-20 17:00:00', 'cLocationId' => 'loc-gent']),
            FakeEntity::create('Meeting', ['id' => 'm3', 'dateStart' => '2026-01-20 17:00:00', 'cLocationId' => 'loc-brugge']),
            FakeEntity::create('Meeting', ['id' => 'm4', 'dateStart' => '2026-01-20 18:00:00', 'cLocationId' => null]),
        ];

        self::assertSame(
            ['loc-gent' => ['18:00' => 2], 'loc-brugge' => ['18:00' => 1], '' => ['19:00' => 1]],
            $this->repository()->getBookingsForDate('cal1', '2026-01-20'),
        );
        // The day is selected in UTC, so meetings between 00:00 and 01:00 (02:00 in summer) Brussels time are not counted.
        self::assertSame([
            'Meeting where {"cCalendarId":"cal1","dateStart>=":"2026-01-20 00:00:00","dateStart<=":"2026-01-20 23:59:59",'
                . '"status!=":["Cancelled","Tentative"]} find',
        ], $this->queries);
    }

    public function testLocationsByIds(): void
    {
        $this->queryResults['CLocation'] = [FakeEntity::create('CLocation', [
            'id' => 'loc1',
            'name' => 'Gent',
            'addressStreet' => 'Straat 1',
            'addressCity' => 'Gent',
            'addressState' => 'OVL',
            'addressCountry' => 'BE',
            'addressPostalCode' => '9000',
        ])];

        self::assertSame([], $this->repository()->getLocationsByIds([]));
        self::assertSame(['loc1' => [
            'id' => 'loc1',
            'name' => 'Gent',
            'addressStreet' => 'Straat 1',
            'addressCity' => 'Gent',
            'addressState' => 'OVL',
            'addressCountry' => 'BE',
            'addressPostalCode' => '9000',
        ]], $this->repository()->getLocationsByIds(['loc1', 'loc1']));
        self::assertSame(['CLocation where {"id":["loc1"]} find'], $this->queries);
    }

    public function testCalendarsForTeam(): void
    {
        $this->queryResults['CAvailability'] = [
            FakeEntity::create('CAvailability', ['id' => 'a1', 'calendarId' => 'cal1']),
            FakeEntity::create('CAvailability', ['id' => 'a2', 'calendarId' => 'cal1']),
            FakeEntity::create('CAvailability', ['id' => 'a3', 'calendarId' => 'cal2']),
            FakeEntity::create('CAvailability', ['id' => 'a4', 'calendarId' => null]),
        ];
        $this->queryResults['CCalendar'] = [FakeEntity::create('CCalendar', ['id' => 'cal1'])];

        $calendars = $this->repository()->getCalendarsForTeam('team1');

        self::assertCount(1, $calendars);
        self::assertSame([
            'CAvailability leftJoin cTeams teamJoin where {"deleted":0,"OR":[{"teamJoin.id":"team1"},{"teamJoin.id":null}]} distinct find',
            'CCalendar where {"isActive":true,"hasPublicLink":true,"id":{"0":"cal1","2":"cal2"}} order name find',
        ], $this->queries);
    }

    public function testCalendarsForTeamWithoutAvailabilities(): void
    {
        self::assertCount(0, $this->repository()->getCalendarsForTeam('team1'));
        self::assertCount(1, $this->queries);
    }

    public function testLocationsForCalendar(): void
    {
        $this->queryResults['CAvailability'] = [
            FakeEntity::create('CAvailability', ['id' => 'a1', 'locationId' => 'loc1']),
            FakeEntity::create('CAvailability', ['id' => 'a2', 'locationId' => null]),
        ];
        $this->queryResults['CLocation'] = [
            FakeEntity::create('CLocation', ['id' => 'loc1', 'name' => 'Gent', 'slug' => 'gent']),
            FakeEntity::create('CLocation', ['id' => 'loc2', 'name' => 'Brugge', 'slug' => null]),
        ];

        $result = $this->repository()->getLocationsForCalendar('cal1', 'team1');

        self::assertSame([['name' => 'Gent', 'slug' => 'gent'], ['name' => 'Brugge', 'slug' => 'loc2']], $result);
        self::assertSame([
            'CAvailability leftJoin cTeams teamJoin where {"calendarId":"cal1","deleted":0,"OR":[{"teamJoin.id":"team1"},{"teamJoin.id":null}]} distinct find',
            'CLocation where {"id":["loc1"]} order name find',
        ], $this->queries);
    }

    public function testLocationsForCalendarWithoutLocations(): void
    {
        $withoutAvailabilities = $this->repository()->getLocationsForCalendar('cal1');

        $this->queryResults['CAvailability'] = [FakeEntity::create('CAvailability', ['id' => 'a1', 'locationId' => null])];
        $withoutLocations = $this->repository()->getLocationsForCalendar('cal1');

        self::assertSame([], $withoutAvailabilities);
        self::assertSame([], $withoutLocations);
        self::assertCount(2, $this->queries);
    }

    public function testPublicLinkStructureGroupsVariantsPerLocation(): void
    {
        $this->queryResults['CAvailability'] = [
            FakeEntity::create('CAvailability', ['id' => 'a1', 'locationId' => 'loc-gent', 'publicSlug' => 'avond', 'name' => 'Avond', 'description' => null]),
            FakeEntity::create('CAvailability', ['id' => 'a2', 'locationId' => 'loc-gent', 'publicSlug' => 'avond', 'name' => 'Avond 2', 'description' => 'Na het werk']),
            FakeEntity::create('CAvailability', ['id' => 'a3', 'locationId' => 'loc-gent', 'publicSlug' => ' ochtend ', 'name' => null, 'description' => null]),
            FakeEntity::create('CAvailability', ['id' => 'a4', 'locationId' => 'loc-brugge', 'publicSlug' => '', 'name' => 'x']),
            FakeEntity::create('CAvailability', ['id' => 'a5', 'locationId' => null, 'publicSlug' => 'online', 'name' => 'Online', 'description' => null]),
        ];
        $this->queryResults['CLocation'] = [
            FakeEntity::create('CLocation', ['id' => 'loc-brugge', 'name' => 'Brugge', 'slug' => 'brugge']),
            FakeEntity::create('CLocation', ['id' => 'loc-gent', 'name' => 'Gent', 'slug' => null]),
        ];

        $result = $this->repository()->getPublicLinkStructureForCalendar('cal1');

        self::assertSame([
            // Without location: no name, so sorted first.
            ['id' => null, 'name' => null, 'slug' => null, 'variants' => [
                ['slug' => 'online', 'label' => 'Online', 'description' => null],
            ]],
            ['id' => 'loc-brugge', 'name' => 'Brugge', 'slug' => 'brugge', 'variants' => []],
            ['id' => 'loc-gent', 'name' => 'Gent', 'slug' => 'loc-gent', 'variants' => [
                // The first availability's name is the label; a later one may fill in the description.
                ['slug' => 'avond', 'label' => 'Avond', 'description' => 'Na het werk'],
                ['slug' => 'ochtend', 'label' => 'ochtend', 'description' => null],
            ]],
        ], $result);
        self::assertSame([
            'CAvailability leftJoin cTeams teamJoin where {"calendarId":"cal1","deleted":0} distinct find',
            'CLocation where {"id":["loc-gent","loc-brugge"]} order name find',
        ], $this->queries);
    }

    public function testFindPublicVariant(): void
    {
        $variant = FakeEntity::create('CAvailability', ['id' => 'a1']);
        $this->queryResults['CAvailability'] = [$variant];

        self::assertSame($variant, $this->repository()->findPublicVariant('cal1', 'avond', 'loc1', 'team1'));
        $this->repository()->findPublicVariant('cal1', 'avond');

        self::assertSame([
            'CAvailability leftJoin cTeams teamJoin where {"calendarId":"cal1","publicSlug":"avond","deleted":0,"locationId":"loc1"}'
                . ' where {"OR":[{"teamJoin.id":"team1"},{"teamJoin.id":null}]} findOne',
            'CAvailability leftJoin cTeams teamJoin where {"calendarId":"cal1","publicSlug":"avond","deleted":0} findOne',
        ], $this->queries);
    }

    private function repository(): CalendarRepository
    {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->recordingRepository($type));

        return new CalendarRepository($entityManager);
    }
}
