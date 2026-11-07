<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ContactManager\Services;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Booking\Services\BookingService;
use Espo\Modules\ContactManager\Services\ContactHomService;
use Espo\Modules\ContactManager\Services\SavingsCardService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use Tests\Support\RecordingQueries;

final class ContactHomServiceTest extends TestCase
{
    use InMemoryEntityManager;
    use RecordingQueries;

    private Entity $contact;

    /** @var list<string> "calendar date time" per created meeting */
    private array $bookedMeetings = [];

    /** @var list<string> */
    private array $redeemed = [];

    private bool $canBookHom = true;

    private bool $cardValid = true;

    protected function setUp(): void
    {
        $this->contact = $this->addRecord(FakeEntity::create('Contact', [
            'id' => 'c1',
            'assignedUserId' => 'coach1',
            'cTypeKlant' => 'klant',
            'cHomPlanned' => false,
            'cRiStatus' => '',
            'cSavingsPointsBalance' => 50,
        ]));
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'cal-hom', 'slug' => 'hom', 'type' => 'iom']));
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'cal-ri', 'slug' => 'ri', 'type' => 'royalty']));
        $this->addRecord(FakeEntity::create('CCalendar', ['id' => 'cal-ks', 'slug' => 'ks', 'type' => 'kickstart']));
    }

    public function testBookHom(): void
    {
        $result = $this->service()->book($this->data(['calendarId' => 'hom', 'selectedDate' => '2026-02-01', 'selectedTime' => '19:00', 'coachNote' => 'Notitie']));

        self::assertSame(['success' => true], $result);
        self::assertSame(['hom 2026-02-01 19:00'], $this->bookedMeetings);
        self::assertTrue($this->contact->get('cHomPlanned'));
        self::assertSame(["HOM: HOM geboekt\nNotitie"], $this->notes());
        self::assertSame('coach1', $this->created[0]->get('assignedUserId'));
        self::assertSame('c1', $this->created[0]->get('contactId'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, string}>
     */
    public static function invalidHomBookings(): iterable
    {
        $slot = ['calendarId' => 'hom', 'selectedDate' => '2026-02-01', 'selectedTime' => '19:00'];

        yield 'already planned' => [['cHomPlanned' => true], $slot, 'Er is al een HOM ingepland voor deze klant.'];
        yield 'not enough points' => [['canBookHom' => false], $slot, 'Een HOM kan pas geboekt worden vanaf 40 geldige spaarkaartpunten, en niet voor PCX of distributeurs.'];
        yield 'no time' => [[], ['calendarId' => 'hom', 'selectedDate' => '2026-02-01'], 'Kies een agenda en tijdstip.'];
        yield 'not a hom calendar' => [[], ['calendarId' => 'ks', 'selectedDate' => '2026-02-01', 'selectedTime' => '19:00'], 'Kies een HOM-agenda.'];
        yield 'unknown calendar' => [[], ['calendarId' => 'weg', 'selectedDate' => '2026-02-01', 'selectedTime' => '19:00'], 'Kies een HOM-agenda.'];
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidHomBookings')]
    public function testInvalidHomBooking(array $state, array $data, string $message): void
    {
        $this->canBookHom = $state['canBookHom'] ?? true;
        $this->contact->set('cHomPlanned', $state['cHomPlanned'] ?? false);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        try {
            $this->service()->book($this->data($data));
        } finally {
            self::assertSame([], $this->bookedMeetings);
            self::assertSame([], $this->savedEntities());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, class-string<\Throwable>, string}>
     */
    public static function invalidContacts(): iterable
    {
        yield 'no id' => [['id' => ''], BadRequest::class, 'Klant ID is verplicht.'];
        yield 'unknown contact' => [['id' => 'weg'], NotFound::class, 'Klant niet gevonden.'];
        yield 'no edit rights' => [['id' => 'c1', 'canEdit' => false], Forbidden::class, 'Geen bewerkrechten voor deze klant.'];
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('invalidContacts')]
    public function testContactChecks(array $data, string $exception, string $message): void
    {
        $service = $this->service($data['canEdit'] ?? true);
        unset($data['canEdit']);

        foreach (['book', 'log', 'logRoyalty'] as $method) {
            try {
                $service->$method((object) $data);
                self::fail("Expected $exception from $method");
            } catch (\Throwable $e) {
                self::assertInstanceOf($exception, $e);
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    public function testLogRequiresAPlannedHom(): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Er is geen HOM ingepland voor deze klant.');

        $this->service()->log($this->data(['outcome' => 'attended', 'homRating' => 'B']));
    }

    public function testInvalidOutcome(): void
    {
        $this->contact->set('cHomPlanned', true);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Ongeldige HOM uitkomst.');

        $this->service()->log($this->data(['outcome' => 'became_client']));
    }

    public function testAttendedWithoutRating(): void
    {
        $this->contact->set('cHomPlanned', true);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('HOM-uitkomst (A/B/C/D) is verplicht.');

        $this->service()->log($this->data(['outcome' => 'attended', 'homRating' => 'E']));
    }

    public function testOutcomeARedeemsPoints(): void
    {
        $this->plannedMeetings();

        $this->service()->log($this->data(['outcome' => 'attended', 'homRating' => 'A', 'coachNote' => 'Top']));

        self::assertSame(['c1'], $this->redeemed);
        self::assertFalse($this->contact->get('cHomPlanned'));
        self::assertSame('Held', $this->meeting('m-hom')->get('status'));
        self::assertSame('Planned', $this->meeting('m-ri')->get('status'));
        self::assertSame(["HOM: HOM aanwezig\nHOM-uitkomst: A\n40 spaarkaartpunten ingewisseld\nTop"], $this->notes());
    }

    /**
     * @return iterable<string, array{bool, int}>
     */
    public static function insufficientPoints(): iterable
    {
        yield 'card expired' => [false, 80];
        yield 'too few points' => [true, 39];
    }

    #[DataProvider('insufficientPoints')]
    public function testOutcomeANeedsFortyValidPoints(bool $cardValid, int $balance): void
    {
        $this->plannedMeetings();
        $this->cardValid = $cardValid;
        $this->contact->set('cSavingsPointsBalance', $balance);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Onvoldoende geldige spaarkaartpunten (minimum 40).');

        try {
            $this->service()->log($this->data(['outcome' => 'attended', 'homRating' => 'A']));
        } finally {
            self::assertTrue($this->contact->get('cHomPlanned'));
            self::assertSame([], $this->redeemed);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function upgrades(): iterable
    {
        yield 'pcx' => ['pcx_15', 'Klant wordt PCX'];
        yield 'distributor' => ['distributor', 'Klant wordt distributeur'];
    }

    #[DataProvider('upgrades')]
    public function testOutcomeBChangesTheClientType(string $type, string $note): void
    {
        $this->plannedMeetings();

        $this->service()->log($this->data(['outcome' => 'attended', 'homRating' => 'B', 'clientType' => $type]));

        self::assertSame($type, $this->contact->get('cTypeKlant'));
        self::assertSame(["HOM: HOM aanwezig\nHOM-uitkomst: B\n$note"], $this->notes());
        self::assertSame([], $this->redeemed);
    }

    public function testOutcomeBNeedsAType(): void
    {
        $this->plannedMeetings();

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Kies of de klant PCX of distributeur wordt.');

        $this->service()->log($this->data(['outcome' => 'attended', 'homRating' => 'B', 'clientType' => 'pcx_35']));
    }

    #[DataProvider('royaltyRatings')]
    public function testOutcomeCOrDBooksARoyaltyMeeting(string $rating): void
    {
        $this->plannedMeetings();

        $this->service()->log($this->data([
            'outcome' => 'attended',
            'homRating' => $rating,
            'riBooking' => (object) ['calendarId' => 'ri', 'selectedDate' => '2026-02-10', 'selectedTime' => '20:00'],
        ]));

        self::assertSame(['ri 2026-02-10 20:00'], $this->bookedMeetings);
        self::assertSame('ri_planned', $this->contact->get('cRiStatus'));
        self::assertSame('Held', $this->meeting('m-hom')->get('status'));
        self::assertSame(["HOM: HOM aanwezig\nHOM-uitkomst: $rating\nRI geboekt op 2026-02-10 om 20:00"], $this->notes());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function royaltyRatings(): iterable
    {
        yield 'C' => ['C'];
        yield 'D' => ['D'];
    }

    public function testOutcomeCNeedsARoyaltyBooking(): void
    {
        $this->plannedMeetings();
        $service = $this->service();

        try {
            $service->log($this->data(['outcome' => 'attended', 'homRating' => 'C']));
            self::fail('Expected BadRequest');
        } catch (BadRequest $e) {
            self::assertSame('Een RI-afspraak is verplicht bij uitkomst C of D.', $e->getMessage());
        }

        $this->expectExceptionMessage('Kies een Royalty (RI) agenda.');

        $service->log($this->data([
            'outcome' => 'attended',
            'homRating' => 'D',
            'riBooking' => (object) ['calendarId' => 'hom', 'selectedDate' => '2026-02-10', 'selectedTime' => '20:00'],
        ]));
    }

    public function testNoShow(): void
    {
        $this->plannedMeetings();

        $this->service()->log($this->data(['outcome' => 'no_show']));

        self::assertFalse($this->contact->get('cHomPlanned'));
        self::assertSame('Not Held', $this->meeting('m-hom')->get('status'));
        self::assertSame(['HOM: HOM niet opgedaagd'], $this->notes());
    }

    public function testCancelAndRescheduleNow(): void
    {
        $this->plannedMeetings();

        $this->service()->log($this->data([
            'outcome' => 'cancelled',
            'cancellationAction' => 'reschedule_now',
            'calendarId' => 'hom',
            'selectedDate' => '2026-02-08',
            'selectedTime' => '18:00',
        ]));

        self::assertSame('Cancelled', $this->meeting('m-hom')->get('status'));
        self::assertSame(['hom 2026-02-08 18:00'], $this->bookedMeetings);
        // A new HOM is planned, so the HOM stays planned.
        self::assertTrue($this->contact->get('cHomPlanned'));
        self::assertSame(['HOM: HOM geannuleerd, opnieuw geboekt op 2026-02-08 om 18:00'], $this->notes());
    }

    public function testCancelAndStop(): void
    {
        $this->plannedMeetings();

        $this->service()->log($this->data(['outcome' => 'cancelled', 'cancellationAction' => 'cancel_stop']));

        self::assertSame('Cancelled', $this->meeting('m-hom')->get('status'));
        self::assertFalse($this->contact->get('cHomPlanned'));
        self::assertSame(['HOM: HOM geannuleerd'], $this->notes());
    }

    public function testCancelNeedsAnAction(): void
    {
        $this->plannedMeetings();

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Vervolgactie is verplicht bij annulering.');

        $this->service()->log($this->data(['outcome' => 'cancelled']));
    }

    public function testOnlyTheFirstPlannedMeetingOfTheTypeIsUpdated(): void
    {
        $this->plannedMeetings(['m-hom', 'm-hom-2']);

        $this->service()->log($this->data(['outcome' => 'no_show']));

        self::assertSame('Not Held', $this->meeting('m-hom')->get('status'));
        self::assertSame('Planned', $this->meeting('m-hom-2')->get('status'));
        self::assertSame(
            'Meeting where {"parentId":"c1","parentType":"Contact","status":["Planned","Tentative"]} order dateStart ASC find',
            $this->queries[0],
        );
    }

    /**
     * @return iterable<string, array{string, string, string, ?string}>
     */
    public static function royaltyOutcomes(): iterable
    {
        // RI status before, outcome => RI status after, note, client type after (null = unchanged)
        yield 'planned: doubt' => ['ri_planned', 'doubt', 'doubt', 'RI: RI aanwezig: twijfel', null];
        yield 'planned: not interested' => ['ri_planned', 'not_interested', 'not_interested', 'RI: RI: geen interesse', null];
        yield 'planned: distributor' => ['ri_planned', 'distributor', 'distributor', 'RI: RI: klant wordt distributeur', 'distributor'];
        yield 'doubt: not interested' => ['doubt', 'not_interested', 'not_interested', 'RI: RI: geen interesse', null];
        yield 'doubt: distributor' => ['doubt', 'distributor', 'distributor', 'RI: RI: klant wordt distributeur', 'distributor'];
    }

    #[DataProvider('royaltyOutcomes')]
    public function testLogRoyalty(string $before, string $outcome, string $after, string $note, ?string $type): void
    {
        $this->plannedMeetings();
        $this->contact->set('cRiStatus', $before);

        $this->service()->logRoyalty($this->data(['outcome' => $outcome]));

        self::assertSame($after, $this->contact->get('cRiStatus'));
        self::assertSame([$note], $this->notes());
        self::assertSame($type ?? 'klant', $this->contact->get('cTypeKlant'));
        // Only a planned RI is marked as held; an RI in doubt was already held.
        self::assertSame($before === 'ri_planned' ? 'Held' : 'Planned', $this->meeting('m-ri')->get('status'));
        self::assertSame('Planned', $this->meeting('m-hom')->get('status'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidRoyaltyOutcomes(): iterable
    {
        yield 'nothing planned' => ['', 'doubt', 'Er is geen RI ingepland of in twijfel voor deze klant.'];
        yield 'not interested before' => ['not_interested', 'distributor', 'Er is geen RI ingepland of in twijfel voor deze klant.'];
        yield 'doubt again' => ['doubt', 'doubt', 'Ongeldige RI uitkomst.'];
        yield 'no show while in doubt' => ['doubt', 'no_show', 'Ongeldige RI uitkomst.'];
        yield 'unknown outcome' => ['ri_planned', 'became_client', 'Ongeldige RI uitkomst.'];
    }

    #[DataProvider('invalidRoyaltyOutcomes')]
    public function testInvalidRoyaltyOutcome(string $status, string $outcome, string $message): void
    {
        $this->contact->set('cRiStatus', $status);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        $this->service()->logRoyalty($this->data(['outcome' => $outcome]));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function royaltyNotHeld(): iterable
    {
        yield 'no show' => ['no_show', 'Not Held', 'RI niet opgedaagd'];
        yield 'cancelled' => ['cancelled', 'Cancelled', 'RI geannuleerd'];
    }

    #[DataProvider('royaltyNotHeld')]
    public function testRoyaltyNotHeldAndRescheduled(string $outcome, string $meetingStatus, string $label): void
    {
        $this->plannedMeetings();
        $this->contact->set('cRiStatus', 'ri_planned');

        $this->service()->logRoyalty($this->data([
            'outcome' => $outcome,
            'cancellationAction' => 'reschedule_now',
            'calendarId' => 'ri',
            'selectedDate' => '2026-02-20',
            'selectedTime' => '20:00',
        ]));

        self::assertSame($meetingStatus, $this->meeting('m-ri')->get('status'));
        self::assertSame(['ri 2026-02-20 20:00'], $this->bookedMeetings);
        self::assertSame('ri_planned', $this->contact->get('cRiStatus'));
        self::assertSame(["RI: $label, opnieuw geboekt op 2026-02-20 om 20:00"], $this->notes());
    }

    #[DataProvider('royaltyNotHeld')]
    public function testRoyaltyNotHeldAndStopped(string $outcome, string $meetingStatus, string $label): void
    {
        $this->plannedMeetings();
        $this->contact->set('cRiStatus', 'ri_planned');

        $this->service()->logRoyalty($this->data(['outcome' => $outcome, 'cancellationAction' => 'cancel_stop']));

        self::assertSame($meetingStatus, $this->meeting('m-ri')->get('status'));
        self::assertSame('', $this->contact->get('cRiStatus'));
        self::assertSame(["RI: $label"], $this->notes());
    }

    public function testRoyaltyNotHeldNeedsAnAction(): void
    {
        $this->contact->set('cRiStatus', 'ri_planned');

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Kies of er een nieuwe RI ingepland wordt.');

        $this->service()->logRoyalty($this->data(['outcome' => 'no_show']));
    }

    public function testRoyaltyRescheduleNeedsARoyaltyCalendar(): void
    {
        $this->contact->set('cRiStatus', 'ri_planned');

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Kies een Royalty (RI) agenda.');

        $this->service()->logRoyalty($this->data([
            'outcome' => 'cancelled',
            'cancellationAction' => 'reschedule_now',
            'calendarId' => 'hom',
            'selectedDate' => '2026-02-20',
            'selectedTime' => '20:00',
        ]));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function data(array $values): object
    {
        return (object) ['id' => 'c1', ...$values];
    }

    /**
     * A planned HOM (legacy 'iom' calendar) and RI meeting of the contact; the HOM marks it as planned.
     *
     * @param list<string> $homMeetings
     */
    private function plannedMeetings(array $homMeetings = ['m-hom']): void
    {
        $this->contact->set('cHomPlanned', true);
        $this->queryResults['Meeting'] = [
            FakeEntity::create('Meeting', ['id' => 'm-ri', 'status' => 'Planned', 'cCalendarId' => 'cal-ri']),
            FakeEntity::create('Meeting', ['id' => 'm-none', 'status' => 'Planned', 'cCalendarId' => null]),
            ...array_map(static fn (string $id) => FakeEntity::create('Meeting', ['id' => $id, 'status' => 'Planned', 'cCalendarId' => 'cal-hom']), $homMeetings),
        ];
    }

    private function meeting(string $id): Entity
    {
        foreach ($this->queryResults['Meeting'] as $meeting) {
            if ($meeting->getId() === $id) {
                return $meeting;
            }
        }

        self::fail("No meeting $id");
    }

    /**
     * @return list<string> "name: note" per created note
     */
    private function notes(): array
    {
        return array_values(array_map(
            static fn (Entity $note) => "{$note->get('name')}: {$note->get('note')}",
            array_filter($this->created, static fn (Entity $e) => $e->getEntityType() === 'CNote'),
        ));
    }

    private function service(bool $canEdit = true): ContactHomService
    {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->recordingRepository($type));

        $bookingService = $this->createStub(BookingService::class);
        $bookingService->method('createInternalMeeting')->willReturnCallback(
            function (string $calendar, Entity $person, string $date, string $time): Entity {
                $this->bookedMeetings[] = "$calendar $date $time";

                return FakeEntity::create('Meeting', ['id' => 'booked']);
            },
        );

        $savingsCard = $this->createStub(SavingsCardService::class);
        $savingsCard->method('canBookHom')->willReturnCallback(fn () => $this->canBookHom);
        $savingsCard->method('isCardValid')->willReturnCallback(fn () => $this->cardValid);
        $savingsCard->method('redeemHomPoints')->willReturnCallback(function (Entity $contact): void {
            $this->redeemed[] = $contact->getId();
        });

        $slugService = $this->createStub(SlugService::class);
        $slugService->method('resolve')->willReturnCallback(function (string $type, ?string $identifier): ?string {
            foreach ($this->records[$type] ?? [] as $id => $entity) {
                if ($identifier === $id || $identifier === $entity->get('slug')) {
                    return $id;
                }
            }

            return null;
        });

        $acl = $this->createStub(Acl::class);
        $acl->method('checkEntityEdit')->willReturn($canEdit);

        return new ContactHomService($entityManager, $bookingService, $savingsCard, $slugService, $acl);
    }
}
