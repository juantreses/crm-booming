<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Booking\Services\BookingService;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Calendar\Services\CalendarService;
use Espo\Modules\Calendar\Services\PublicCalendarAccess;
use Espo\Modules\LeadManager\Services\LeadService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use TypeError;

final class BookingServiceTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var list<array<string, mixed>> what CalendarService::getAvailableSlots() returns */
    private array $slots = [];

    /** @var list<string> "calendarId date" per slot lookup */
    private array $slotLookups = [];

    /** @var list<string> "meeting.link -> target (columns)" per relate/relateById */
    private array $relations = [];

    private Entity $person;

    protected function setUp(): void
    {
        $this->person = FakeEntity::create('Lead', ['id' => 'lead1', 'name' => 'Jan Peeters', 'assignedUserId' => 'coach1', 'cStage' => null]);
        $this->slots = [
            ['start' => '18:00', 'isBookable' => true, 'availabilityId' => 'av1', 'locationId' => 'loc1'],
            ['start' => '19:00', 'isBookable' => false, 'availabilityId' => 'av1', 'locationId' => 'loc1'],
            ['start' => '19:00', 'isBookable' => true, 'availabilityId' => 'av2', 'locationId' => 'loc2'],
        ];
    }

    public function testPublicBookingCreatesMeetingInUtc(): void
    {
        $calendar = $this->calendar(['type' => 'spark', 'duration' => 90]);

        $result = $this->service($calendar)->processBooking($this->bookingData(['date' => '2026-01-20', 'time' => '19:00']));

        self::assertSame(['success' => true, 'id' => 'new1'], $result);
        self::assertSame(['cal1 2026-01-20'], $this->slotLookups);

        $meeting = $this->created[0];
        self::assertSame('Meeting', $meeting->getEntityType());
        self::assertSame('Spark - Jan Peeters', $meeting->get('name'));
        self::assertSame('Planned', $meeting->get('status'));
        // 19:00 Brussels in winter is 18:00 UTC.
        self::assertSame('2026-01-20 18:00:00', $meeting->get('dateStart'));
        self::assertSame('2026-01-20 19:30:00', $meeting->get('dateEnd'));
        self::assertSame('Graag om 19u', $meeting->get('description'));
        self::assertSame('cal1', $meeting->get('cCalendarId'));
        self::assertSame('lead1', $meeting->get('parentId'));
        self::assertSame('Lead', $meeting->get('parentType'));
        self::assertSame('coach1', $meeting->get('assignedUserId'));
        self::assertSame('loc2', $meeting->get('cLocationId'));
        self::assertSame('av2', $meeting->get('cAvailabilityId'));
        self::assertSame(['new1.leads -> lead1 {"status":"Accepted"}', 'new1.users -> coach1'], $this->relations);
    }

    public function testSummerTimeAndDefaultDuration(): void
    {
        $calendar = $this->calendar(['type' => 'bws', 'duration' => null]);

        $this->service($calendar)->processBooking($this->bookingData(['date' => '2026-07-01', 'time' => '18:00']));

        self::assertSame('2026-07-01 16:00:00', $this->created[0]->get('dateStart'));
        self::assertSame('2026-07-01 17:00:00', $this->created[0]->get('dateEnd'));
    }

    public function testCalendarNeedingApprovalCreatesTentativeMeetingForCalendarOwner(): void
    {
        $calendar = $this->calendar(['type' => 'kickstart', 'needsApproval' => true, 'assignedUserId' => 'owner1']);

        $this->service($calendar)->processBooking($this->bookingData());

        self::assertSame('Tentative', $this->created[0]->get('status'));
        self::assertSame('owner1', $this->created[0]->get('assignedUserId'));
        self::assertSame('Kickstart - Jan Peeters', $this->created[0]->get('name'));
    }

    public function testPersonWithoutCoachIsOnlyLinkedAsAttendee(): void
    {
        $this->person->set('assignedUserId', null);

        $this->service($this->calendar(['type' => 'spark']))->processBooking($this->bookingData());

        self::assertSame(['new1.leads -> lead1 {"status":"Accepted"}'], $this->relations);
    }

    public function testContactIsLinkedAsContactAndKeepsItsData(): void
    {
        $this->person = FakeEntity::create('Contact', ['id' => 'contact1', 'name' => 'Klant', 'status' => 'Active']);

        $this->service($this->calendar(['type' => 'spark']))->processBooking($this->bookingData());

        self::assertSame(['new1.contacts -> contact1 {"status":"Accepted"}'], $this->relations);
        self::assertSame('Active', $this->person->get('status'));
        self::assertSame(['Meeting:new1'], $this->savedEntities());
    }

    /**
     * @return iterable<string, array{string, ?string, string, ?string}>
     */
    public static function leadUpdates(): iterable
    {
        // calendar type, stage before => status, stage, meeting type after
        yield 'spark' => ['spark', 'follow_up', 'intro_scheduled', 'spark'];
        yield 'hom via legacy iom' => ['iom', null, 'intro_scheduled', 'hom'];
        yield 'kickstart keeps stage' => ['kickstart', 'book_ks', 'book_ks', null];
        yield 'kickstart without stage starts at to call' => ['kickstart', null, 'to_call', null];
    }

    #[DataProvider('leadUpdates')]
    public function testPublicBookingUpdatesLead(string $calendarType, ?string $stageBefore, string $stage, ?string $meetingType): void
    {
        $this->person->set(['status' => 'Dead', 'cStage' => $stageBefore]);

        $this->service($this->calendar(['type' => $calendarType]))->processBooking($this->bookingData());

        self::assertSame('Assigned', $this->person->get('status'));
        self::assertSame($stage, $this->person->get('cStage'));
        self::assertSame($meetingType, $this->person->get('cMeetingType'));
        self::assertSame(['Meeting:new1', 'Lead:lead1'], $this->savedEntities());
    }

    public function testBookingMatchesTheRequestedAvailability(): void
    {
        $this->service($this->calendar(['type' => 'spark']))
            ->processBooking($this->bookingData(['time' => '18:00', 'availabilityId' => 'av1']));

        self::assertSame('av1', $this->created[0]->get('cAvailabilityId'));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function unavailableSlots(): iterable
    {
        yield 'no slot at that time' => [['time' => '20:00']];
        yield 'other availability' => [['time' => '18:00', 'availabilityId' => 'av2']];
        yield 'not bookable' => [['time' => '19:00', 'availabilityId' => 'av1']];
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('unavailableSlots')]
    public function testUnavailableSlotIsAConflict(array $data): void
    {
        $leadService = $this->createMock(LeadService::class);
        $leadService->expects(self::never())->method('findOrCreate');

        $this->expectException(Conflict::class);
        $this->expectExceptionMessage('Helaas, tijdstip is niet (meer) beschikbaar.');

        try {
            $this->service($this->calendar(['type' => 'spark']), $leadService)->processBooking($this->bookingData($data));
        } finally {
            self::assertSame([], $this->created);
        }
    }

    public function testInternalMeetingResolvesCalendarSlug(): void
    {
        $this->addRecord($this->calendar(['type' => 'kickstart', 'slug' => 'ks-gent']));

        $meeting = $this->service()->createInternalMeeting('ks-gent', $this->person, '2026-01-20', '18:00', 'Notitie');

        self::assertSame('Kickstart - Jan Peeters', $meeting->get('name'));
        self::assertSame('Notitie', $meeting->get('description'));
        self::assertSame('av1', $meeting->get('cAvailabilityId'));
        self::assertSame(['cal1 2026-01-20'], $this->slotLookups);
        // Internal bookings leave the lead's status to the caller.
        self::assertSame(['Meeting:new1'], $this->savedEntities());
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function meetingNames(): iterable
    {
        yield 'online kickstart' => ['online', false, 'Online Kickstart - Jan Peeters'];
        yield 'follow-up' => ['followup', false, 'FU - Jan Peeters'];
        yield 'royalty' => ['royalty', false, 'Royalty - Jan Peeters'];
        yield 'spark cup wins over type' => ['workout', true, 'SPARK cup - Jan Peeters'];
        yield 'workout' => ['workout', false, 'Workout - Jan Peeters'];
    }

    #[DataProvider('meetingNames')]
    public function testMeetingName(string $calendarType, bool $isSparkCup, string $name): void
    {
        $this->addRecord($this->calendar(['type' => $calendarType]));

        $meeting = $this->service()->createInternalMeeting('cal1', $this->person, '2026-01-20', '18:00', null, $isSparkCup);

        self::assertSame($name, $meeting->get('name'));
        self::assertSame('', $meeting->get('description'));
    }

    public function testInternalMeetingWithUnknownCalendar(): void
    {
        $this->addRecord($this->calendar(['type' => 'spark']));
        $slugService = $this->createStub(SlugService::class);
        $slugService->method('resolve')->willReturn('missing');

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage("Kalender met ID 'missing' niet gevonden.");

        $this->service(slugService: $slugService)->createInternalMeeting('missing', $this->person, '2026-01-20', '18:00');
    }

    public function testInternalMeetingWithUnknownCalendarSlugFailsWithTypeError(): void
    {
        // Same issue as fixed in LeadManager: SlugService returns null for an unknown slug,
        // and getEntityById() requires a string, so the NotFound message is never reached.
        $this->expectException(TypeError::class);

        $this->service()->createInternalMeeting('bestaat-niet', $this->person, '2026-01-20', '18:00');
    }

    public function testWorkoutForLeadIsAlwaysASparkCup(): void
    {
        $this->addRecord($this->person);
        $this->addRecord($this->calendar(['type' => 'workout']));

        $result = $this->service()->bookWorkout([
            'entityType' => 'Lead',
            'entityId' => 'lead1',
            'selectedDate' => '2026-01-20',
            'selectedTime' => '18:00',
            'isSparkCup' => false,
            'coachNote' => 'Notitie',
        ]);

        self::assertSame(['id' => 'new1'], $result);
        self::assertSame('SPARK cup - Jan Peeters', $this->created[0]->get('name'));
        self::assertSame('Notitie', $this->created[0]->get('description'));
    }

    public function testWorkoutForContactFollowsTheSparkCupFlag(): void
    {
        $this->addRecord(FakeEntity::create('Contact', ['id' => 'contact1', 'name' => 'Klant']));
        $this->addRecord($this->calendar(['type' => 'workout']));
        $data = ['entityType' => 'Contact', 'entityId' => 'contact1', 'selectedDate' => '2026-01-20', 'selectedTime' => '18:00'];

        $this->service()->bookWorkout($data);
        $this->service()->bookWorkout([...$data, 'isSparkCup' => true]);

        self::assertSame('Workout - Klant', $this->created[0]->get('name'));
        self::assertSame('SPARK cup - Klant', $this->created[1]->get('name'));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, class-string<\Throwable>, string}>
     */
    public static function invalidInternalBookings(): iterable
    {
        yield 'workout for user' => ['bookWorkout', ['entityType' => 'User', 'entityId' => 'u1'], BadRequest::class, 'Ongeldig entiteitstype voor het boeken van een workout.'];
        yield 'workout without id' => ['bookWorkout', ['entityType' => 'Lead'], BadRequest::class, 'Ongeldig entiteitstype voor het boeken van een workout.'];
        yield 'workout for unknown lead' => ['bookWorkout', ['entityType' => 'Lead', 'entityId' => 'missing'], NotFound::class, 'Lead niet gevonden.'];
        yield 'follow-up for lead' => ['bookFollowUp', ['entityType' => 'Lead', 'entityId' => 'lead1'], BadRequest::class, 'Een follow-up kan enkel voor klanten geboekt worden.'];
        yield 'follow-up for unknown contact' => ['bookFollowUp', ['entityType' => 'Contact', 'entityId' => 'missing'], NotFound::class, 'Contact niet gevonden.'];
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('invalidInternalBookings')]
    public function testInvalidInternalBooking(string $method, array $data, string $exception, string $message): void
    {
        $this->addRecord($this->person);

        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $this->service()->$method([...$data, 'selectedDate' => '2026-01-20', 'selectedTime' => '18:00']);
    }

    public function testNoActiveWorkoutOrFollowUpCalendar(): void
    {
        $this->addRecord($this->person);
        $this->addRecord(FakeEntity::create('Contact', ['id' => 'contact1']));
        $repository = $this->createStub(CalendarRepository::class);
        $repository->method('findActiveCalendarByType')->willReturn(null);
        $service = $this->service(calendarRepository: $repository);

        try {
            $service->bookWorkout(['entityType' => 'Lead', 'entityId' => 'lead1']);
            self::fail('Expected NotFound');
        } catch (NotFound $e) {
            self::assertSame('Geen actieve workout agenda gevonden.', $e->getMessage());
        }

        $this->expectException(NotFound::class);
        $this->expectExceptionMessage('Geen actieve follow-up agenda gevonden.');

        $service->bookFollowUp(['entityType' => 'Contact', 'entityId' => 'contact1']);
    }

    public function testFollowUpForContact(): void
    {
        $this->addRecord(FakeEntity::create('Contact', ['id' => 'contact1', 'name' => 'Klant']));
        $this->addRecord($this->calendar(['type' => 'followup']));

        $result = $this->service()->bookFollowUp([
            'entityType' => 'Contact',
            'entityId' => 'contact1',
            'selectedDate' => '2026-01-20',
            'selectedTime' => '18:00',
        ]);

        self::assertSame(['id' => 'new1'], $result);
        self::assertSame('FU - Klant', $this->created[0]->get('name'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function calendar(array $values): Entity
    {
        return FakeEntity::create('CCalendar', ['id' => 'cal1', 'needsApproval' => false, ...$values]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function bookingData(array $overrides = []): array
    {
        return [
            'calendarId' => 'spark-gent',
            'date' => '2026-01-20',
            'time' => '19:00',
            'email' => 'jan@test.be',
            'note' => 'Graag om 19u',
            ...$overrides,
        ];
    }

    private function service(
        ?Entity $publicCalendar = null,
        ?LeadService $leadService = null,
        ?SlugService $slugService = null,
        ?CalendarRepository $calendarRepository = null,
    ): BookingService {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn () => $this->meetingRepository());

        $calendarService = $this->createStub(CalendarService::class);
        $calendarService->method('getAvailableSlots')->willReturnCallback(function (string $calendarId, string $date): array {
            $this->slotLookups[] = "$calendarId $date";

            return $this->slots;
        });

        if (!$calendarRepository) {
            $calendarRepository = $this->createStub(CalendarRepository::class);
            $calendarRepository->method('findActiveCalendarByType')->willReturnCallback(
                fn (string $type) => array_values(array_filter(
                    $this->records['CCalendar'] ?? [],
                    static fn (Entity $c) => $c->get('type') === $type,
                ))[0] ?? null,
            );
        }

        if (!$leadService) {
            $leadService = $this->createStub(LeadService::class);
            $leadService->method('findOrCreate')->willReturnCallback(fn () => $this->person);
        }

        if (!$slugService) {
            $slugService = $this->createStub(SlugService::class);
            $slugService->method('resolve')->willReturnCallback(function (string $type, ?string $identifier): ?string {
                foreach ($this->records[$type] ?? [] as $id => $entity) {
                    if ($identifier === $id || $identifier === $entity->get('slug')) {
                        return $id;
                    }
                }

                return null;
            });
        }

        $publicCalendarAccess = $this->createStub(PublicCalendarAccess::class);
        if ($publicCalendar) {
            $publicCalendarAccess->method('resolveAccessible')->willReturn($publicCalendar);
        }

        return new BookingService(
            $entityManager,
            $calendarService,
            $calendarRepository,
            $leadService,
            $slugService,
            $publicCalendarAccess,
        );
    }

    private function meetingRepository(): RDBRepository
    {
        $repository = $this->createStub(RDBRepository::class);
        $repository->method('getRelation')->willReturnCallback(function (Entity $meeting, string $link): RDBRelation {
            $relation = $this->createStub(RDBRelation::class);
            $relation->method('relate')->willReturnCallback(function (Entity $target, ?array $columns = null) use ($meeting, $link): void {
                $this->relations[] = "{$meeting->getId()}.$link -> {$target->getId()}" . ($columns ? ' ' . json_encode($columns) : '');
            });
            $relation->method('relateById')->willReturnCallback(function (string $id) use ($meeting, $link): void {
                $this->relations[] = "{$meeting->getId()}.$link -> $id";
            });

            return $relation;
        });

        return $repository;
    }
}
