<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Hooks;

use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\Modules\Booking\Hooks\Meeting\GroupAssignment;
use Espo\Modules\Booking\Hooks\Meeting\TelegramNotification;
use Espo\Modules\Telegram\Services\TelegramService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class TelegramAndGroupHooksTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var list<string> */
    private array $messages = [];

    protected function setUp(): void
    {
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'ks', 'type' => 'kickstart']));
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'online', 'type' => 'online']));
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'spark', 'type' => 'spark']));
        $this->addRecord(FakeEntity::create('User', ['id' => 'coach1', 'cTelegramUsername' => '@coachjan']));
        $this->addRecord(FakeEntity::create('Lead', [
            'id' => 'lead1',
            'name' => 'Jan Peeters',
            'emailAddress' => 'jan@test.be',
            'assignedUserId' => 'coach1',
            'assignedUserName' => 'Coach Jan',
        ]));
    }

    public function testNewKickstartIsAnnounced(): void
    {
        $this->afterSave(self::newMeetingStatic('ks', 'Planned'));

        self::assertSame(
            ["🚀 <b>Nieuwe Kickstart Gepland!</b>\n\n👤 Jan Peeters\n✉️ jan@test.be\n📅 20/01/2026 19:00\n🎓 Coach Jan\n\n"],
            $this->messages,
        );
    }

    public function testNewTentativeOnlineKickstartWithoutEmailIsAnnounced(): void
    {
        $this->records['Lead']['lead1']->set('emailAddress', null);

        $this->afterSave(self::newMeetingStatic('online', 'Tentative'));

        self::assertSame(
            ["🚀 <b>Nieuwe Online Kickstart Gepland!</b>\n\n👤 Jan Peeters\n📅 20/01/2026 19:00\n🎓 Coach Jan\n\n"],
            $this->messages,
        );
    }

    /**
     * @return iterable<string, array{Entity}>
     */
    public static function silentSaves(): iterable
    {
        yield 'new spark' => [self::newMeetingStatic('spark', 'Planned')];
        yield 'existing kickstart edited' => [FakeEntity::fetched('Meeting', self::meetingValues('ks', 'Planned'), ['description' => 'x'])];
        yield 'new kickstart without person' => [FakeEntity::create('Meeting', [...self::meetingValues('ks', 'Planned'), 'parentId' => null])];
        yield 'new held meeting' => [self::newMeetingStatic('ks', 'Held')];
        yield 'new cancelled meeting' => [self::newMeetingStatic('ks', 'Cancelled')];
    }

    #[DataProvider('silentSaves')]
    public function testNoMessage(Entity $meeting): void
    {
        $this->afterSave($meeting);

        self::assertSame([], $this->messages);
    }

    public function testCancellationTagsTheCoach(): void
    {
        $this->afterSave(FakeEntity::fetched('Meeting', self::meetingValues('spark', 'Planned'), ['status' => 'Cancelled']));

        self::assertSame(["🚨 <b>Spark Geannuleerd!</b> @coachjan\n\n👤 Jan Peeters\n📅 20/01/2026 19:00\n"], $this->messages);
    }

    public function testCancelledKickstartUsesItsLabel(): void
    {
        $this->afterSave(FakeEntity::fetched('Meeting', self::meetingValues('online', 'Planned'), ['status' => 'Cancelled']));

        self::assertStringStartsWith('🚨 <b>Online Kickstart Geannuleerd!</b> @coachjan', $this->messages[0]);
    }

    public function testCancellationWithoutCalendarOrPerson(): void
    {
        $this->afterSave(FakeEntity::fetched('Meeting', [
            'id' => 'm1',
            'status' => 'Planned',
            'cCalendarId' => null,
            'dateStart' => null,
        ], ['status' => 'Cancelled']));

        self::assertSame(["🚨 <b>Afspraak Geannuleerd!</b>\n\n📅 Onbekend\n"], $this->messages);
    }

    public function testSummerTimeInMessage(): void
    {
        $this->afterSave(FakeEntity::create('Meeting', [...self::meetingValues('ks', 'Planned'), 'dateStart' => '2026-07-01 17:00:00']));

        self::assertStringContainsString('📅 01/07/2026 19:00', $this->messages[0]);
    }

    public function testGroupAssignmentSyncsTeamFromPerson(): void
    {
        $meeting = self::newMeetingStatic('ks', 'Planned');
        $calls = [];
        $service = $this->createStub(GroupAssignmentService::class);
        $service->method('syncGroupsFromFields')->willReturnCallback(
            function (Entity $destination, array $fields, ?Entity $source) use (&$calls): void {
                $calls[] = [$destination->getId(), $fields, $source?->getId()];
            },
        );

        (new GroupAssignment($this->inMemoryEntityManager(), $service))->beforeSave($meeting, SaveOptions::fromAssoc([]));

        self::assertSame([['m1', ['cTeam'], 'lead1']], $calls);
    }

    /**
     * @return iterable<string, array{array<string, ?string>}>
     */
    public static function meetingsWithoutPerson(): iterable
    {
        yield 'no parent type' => [['parentType' => null]];
        yield 'no parent id' => [['parentId' => null]];
        yield 'unknown parent' => [['parentId' => 'gone']];
    }

    /**
     * @param array<string, ?string> $overrides
     */
    #[DataProvider('meetingsWithoutPerson')]
    public function testGroupAssignmentSkipsMeetingWithoutPerson(array $overrides): void
    {
        $service = $this->createMock(GroupAssignmentService::class);
        $service->expects(self::never())->method('syncGroupsFromFields');

        $meeting = FakeEntity::create('Meeting', [...self::meetingValues('ks', 'Planned'), ...$overrides]);

        (new GroupAssignment($this->inMemoryEntityManager(), $service))->beforeSave($meeting, SaveOptions::fromAssoc([]));
    }

    public function testGroupAssignmentErrorIsLoggedNotThrown(): void
    {
        $originalLog = $GLOBALS['log'] ?? null;
        $log = $this->createMock(Log::class);
        $log->expects(self::once())->method('error')->with('Meeting Before Save Hook error: no teams');
        $GLOBALS['log'] = $log;

        $service = $this->createStub(GroupAssignmentService::class);
        $service->method('syncGroupsFromFields')->willThrowException(new RuntimeException('no teams'));

        try {
            (new GroupAssignment($this->inMemoryEntityManager(), $service))
                ->beforeSave(self::newMeetingStatic('ks', 'Planned'), SaveOptions::fromAssoc([]));
        } finally {
            $GLOBALS['log'] = $originalLog;
        }
    }

    /**
     * @return array<string, ?string>
     */
    private static function meetingValues(string $calendarId, string $status): array
    {
        return [
            'id' => 'm1',
            'status' => $status,
            'cCalendarId' => $calendarId,
            'parentType' => 'Lead',
            'parentId' => 'lead1',
            // 19:00 in Brussels (winter).
            'dateStart' => '2026-01-20 18:00:00',
        ];
    }

    private static function newMeetingStatic(string $calendarId, string $status): Entity
    {
        return FakeEntity::create('Meeting', self::meetingValues($calendarId, $status));
    }

    private function afterSave(Entity $meeting): void
    {
        $telegram = $this->createStub(TelegramService::class);
        $telegram->method('sendMessage')->willReturnCallback(function (string $message): bool {
            $this->messages[] = $message;

            return true;
        });

        (new TelegramNotification($this->inMemoryEntityManager(), $telegram))->afterSave($meeting, SaveOptions::fromAssoc([]));
    }
}
