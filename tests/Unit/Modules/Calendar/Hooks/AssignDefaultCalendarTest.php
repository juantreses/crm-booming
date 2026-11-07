<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Calendar\Hooks;

use Espo\Modules\Calendar\Hooks\Meeting\AssignDefaultCalendar;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use Tests\Support\RecordingQueries;

final class AssignDefaultCalendarTest extends TestCase
{
    use InMemoryEntityManager;
    use RecordingQueries;

    protected function setUp(): void
    {
        $this->queryResults['CCalendar'] = [FakeEntity::create('CCalendar', ['id' => 'cal-coach'])];
    }

    public function testNewMeetingWithoutCalendarGetsTheCalendarOfItsUser(): void
    {
        $meeting = FakeEntity::create('Meeting', ['id' => 'm1', 'assignedUserId' => 'coach1', 'cCalendarId' => null]);

        $this->beforeSave($meeting);

        self::assertSame('cal-coach', $meeting->get('cCalendarId'));
        self::assertSame(['CCalendar where {"assignedUserId":"coach1","isActive":true} findOne'], $this->queries);
    }

    public function testNewMeetingWithACalendarAlsoGetsTheCalendarOfItsUser(): void
    {
        // The class comment says "only if no calendar is already set", but a new meeting
        // (e.g. booked on the kickstart calendar) gets the calendar of its assigned user.
        $meeting = FakeEntity::create('Meeting', ['id' => 'm1', 'assignedUserId' => 'coach1', 'cCalendarId' => 'cal-kickstart']);

        $this->beforeSave($meeting);

        self::assertSame('cal-coach', $meeting->get('cCalendarId'));
    }

    public function testReassignedMeetingGetsTheCalendarOfTheNewUser(): void
    {
        $meeting = FakeEntity::fetched('Meeting', ['id' => 'm1', 'assignedUserId' => 'coach1', 'cCalendarId' => 'cal-kickstart'], ['assignedUserId' => 'coach2']);

        $this->beforeSave($meeting);

        self::assertSame('cal-coach', $meeting->get('cCalendarId'));
        self::assertSame(['CCalendar where {"assignedUserId":"coach2","isActive":true} findOne'], $this->queries);
    }

    public function testExistingMeetingWithCalendarIsLeftAlone(): void
    {
        $meeting = FakeEntity::fetched('Meeting', ['id' => 'm1', 'assignedUserId' => 'coach1', 'cCalendarId' => 'cal-kickstart'], ['description' => 'x']);

        $this->beforeSave($meeting);

        self::assertSame('cal-kickstart', $meeting->get('cCalendarId'));
        self::assertSame([], $this->queries);
    }

    public function testExistingMeetingWithoutCalendarGetsOne(): void
    {
        $meeting = FakeEntity::fetched('Meeting', ['id' => 'm1', 'assignedUserId' => 'coach1', 'cCalendarId' => null]);

        $this->beforeSave($meeting);

        self::assertSame('cal-coach', $meeting->get('cCalendarId'));
    }

    public function testWithoutUserOrUserCalendarNothingChanges(): void
    {
        $withoutUser = FakeEntity::create('Meeting', ['id' => 'm1', 'assignedUserId' => null, 'cCalendarId' => 'cal-kickstart']);
        $this->beforeSave($withoutUser);
        self::assertSame('cal-kickstart', $withoutUser->get('cCalendarId'));
        self::assertSame([], $this->queries);

        $this->queryResults['CCalendar'] = [];
        $userWithoutCalendar = FakeEntity::create('Meeting', ['id' => 'm2', 'assignedUserId' => 'coach1', 'cCalendarId' => 'cal-kickstart']);
        $this->beforeSave($userWithoutCalendar);
        self::assertSame('cal-kickstart', $userWithoutCalendar->get('cCalendarId'));
    }

    private function beforeSave(Entity $meeting): void
    {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->recordingRepository($type));

        (new AssignDefaultCalendar($entityManager))->beforeSave($meeting, SaveOptions::fromAssoc([]));
    }
}
