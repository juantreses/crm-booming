<?php

namespace Espo\Modules\ClientManager\Services;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Enums\HomRating;
use Espo\Custom\Enums\IntroMeetingType;
use Espo\Modules\Booking\Services\BookingService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * HOM for clients (Contact): booked from the savings card, logged like a lead HOM.
 * Outcome A redeems savings card points, B makes the client PCX or distributor,
 * C/D book a Royalty (RI) meeting right away, whose outcome is tracked in the RI status.
 */
readonly class ContactHomService
{
    /** Client types a client can switch to with outcome B */
    public const UPGRADE_TYPES = ['pcx_15', 'distributor'];

    private const RI_PLANNED = 'ri_planned';
    private const RI_DOUBT = 'doubt';
    private const RI_NOT_INTERESTED = 'not_interested';
    private const RI_DISTRIBUTOR = 'distributor';

    public function __construct(
        private EntityManager $entityManager,
        private BookingService $bookingService,
        private SavingsCardService $savingsCardService,
        private SlugService $slugService,
        private Acl $acl,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function book(\StdClass $data): array
    {
        $contact = $this->getContact($data);

        if ($contact->get('cHomPlanned')) {
            throw new BadRequest('Er is al een HOM ingepland voor deze klant.');
        }

        if (!$this->savingsCardService->canBookHom($contact)) {
            throw new BadRequest(
                'Een HOM kan pas geboekt worden vanaf ' . SavingsCardService::HOM_POINTS .
                ' geldige spaarkaartpunten, en niet voor PCX of distributeurs.'
            );
        }

        $booking = $this->getBooking($data->calendarId ?? null, $data->selectedDate ?? null, $data->selectedTime ?? null);
        $this->assertCalendarType($booking['calendarId'], 'hom');

        $this->bookingService->createInternalMeeting(
            $booking['calendarId'],
            $contact,
            $booking['selectedDate'],
            $booking['selectedTime'],
            $data->coachNote ?? null
        );

        $contact->set('cHomPlanned', true);
        $this->entityManager->saveEntity($contact);

        $this->addNote($contact, 'HOM geboekt', $data->coachNote ?? null);

        return ['success' => true];
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function log(\StdClass $data): array
    {
        $contact = $this->getContact($data);

        if (!$contact->get('cHomPlanned')) {
            throw new BadRequest('Er is geen HOM ingepland voor deze klant.');
        }

        return match ($data->outcome ?? null) {
            'attended' => $this->logAttended($contact, $data),
            'no_show' => $this->logNoShow($contact, $data),
            'cancelled' => $this->logCancelled($contact, $data),
            default => throw new BadRequest('Ongeldige HOM uitkomst.'),
        };
    }

    private function logAttended(Entity $contact, \StdClass $data): array
    {
        $rating = HomRating::tryFrom((string) ($data->homRating ?? ''));
        if (!$rating) {
            throw new BadRequest('HOM-uitkomst (A/B/C/D) is verplicht.');
        }

        // Validate everything before changing anything
        $newType = null;
        $riBooking = null;

        if (
            $rating === HomRating::A
            && (
                !$this->savingsCardService->isCardValid($contact)
                || (int) $contact->get('cSavingsPointsBalance') < SavingsCardService::HOM_POINTS
            )
        ) {
            throw new BadRequest('Onvoldoende geldige spaarkaartpunten (minimum ' . SavingsCardService::HOM_POINTS . ').');
        }

        if ($rating === HomRating::B) {
            $newType = $data->clientType ?? null;
            if (!in_array($newType, self::UPGRADE_TYPES, true)) {
                throw new BadRequest('Kies of de klant PCX of distributeur wordt.');
            }
        }

        if ($rating->leadsToRoyalty()) {
            $riBooking = $this->getBooking(
                $data->riBooking->calendarId ?? null,
                $data->riBooking->selectedDate ?? null,
                $data->riBooking->selectedTime ?? null,
                'Een RI-afspraak is verplicht bij uitkomst C of D.'
            );
            $this->assertCalendarType($riBooking['calendarId'], 'royalty');
        }

        $noteText = "HOM aanwezig\nHOM-uitkomst: {$rating->value}";

        if ($rating === HomRating::A) {
            $this->savingsCardService->redeemHomPoints($contact);
            $noteText .= "\n" . SavingsCardService::HOM_POINTS . ' spaarkaartpunten ingewisseld';
        }

        if ($newType) {
            $contact->set('cTypeKlant', $newType);
            $noteText .= $newType === 'distributor' ? "\nKlant wordt distributeur" : "\nKlant wordt PCX";
        }

        if ($riBooking) {
            $this->bookingService->createInternalMeeting(
                $riBooking['calendarId'],
                $contact,
                $riBooking['selectedDate'],
                $riBooking['selectedTime']
            );
            $contact->set('cRiStatus', self::RI_PLANNED);
            $noteText .= "\nRI geboekt op {$riBooking['selectedDate']} om {$riBooking['selectedTime']}";
        }

        $this->closeHom($contact, 'Held');
        $this->addNote($contact, $noteText, $data->coachNote ?? null);

        return ['success' => true];
    }

    private function logNoShow(Entity $contact, \StdClass $data): array
    {
        $this->closeHom($contact, 'Not Held');
        $this->addNote($contact, 'HOM niet opgedaagd', $data->coachNote ?? null);

        return ['success' => true];
    }

    private function logCancelled(Entity $contact, \StdClass $data): array
    {
        $action = $data->cancellationAction ?? null;

        if ($action === 'reschedule_now') {
            $booking = $this->getBooking($data->calendarId ?? null, $data->selectedDate ?? null, $data->selectedTime ?? null);
            $this->assertCalendarType($booking['calendarId'], 'hom');

            $this->markPlannedMeeting($contact, 'hom', 'Cancelled');
            $this->bookingService->createInternalMeeting(
                $booking['calendarId'],
                $contact,
                $booking['selectedDate'],
                $booking['selectedTime']
            );
            $this->addNote(
                $contact,
                "HOM geannuleerd, opnieuw geboekt op {$booking['selectedDate']} om {$booking['selectedTime']}",
                $data->coachNote ?? null
            );

            return ['success' => true];
        }

        if ($action !== 'cancel_stop') {
            throw new BadRequest('Vervolgactie is verplicht bij annulering.');
        }

        $this->closeHom($contact, 'Cancelled');
        $this->addNote($contact, 'HOM geannuleerd', $data->coachNote ?? null);

        return ['success' => true];
    }

    /**
     * Log the RI booked after HOM outcome C/D, or follow up an RI in doubt.
     *
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function logRoyalty(\StdClass $data): array
    {
        $contact = $this->getContact($data);
        $status = $contact->get('cRiStatus');
        $outcome = $data->outcome ?? null;

        $allowed = match ($status) {
            self::RI_PLANNED => ['doubt', 'not_interested', 'distributor', 'no_show', 'cancelled'],
            self::RI_DOUBT => ['not_interested', 'distributor'],
            default => throw new BadRequest('Er is geen RI ingepland of in twijfel voor deze klant.'),
        };

        if (!in_array($outcome, $allowed, true)) {
            throw new BadRequest('Ongeldige RI uitkomst.');
        }

        if ($outcome === 'no_show' || $outcome === 'cancelled') {
            return $this->logRoyaltyNotHeld($contact, $data, $outcome);
        }

        if ($status === self::RI_PLANNED) {
            $this->markPlannedMeeting($contact, 'royalty', 'Held');
        }

        $noteText = match ($outcome) {
            'doubt' => 'RI aanwezig: twijfel',
            'not_interested' => 'RI: geen interesse',
            'distributor' => 'RI: klant wordt distributeur',
        };

        if ($outcome === 'distributor') {
            $contact->set('cTypeKlant', 'distributor');
        }

        $contact->set('cRiStatus', match ($outcome) {
            'doubt' => self::RI_DOUBT,
            'not_interested' => self::RI_NOT_INTERESTED,
            'distributor' => self::RI_DISTRIBUTOR,
        });
        $this->entityManager->saveEntity($contact);

        $this->addNote($contact, $noteText, $data->coachNote ?? null, 'RI');

        return ['success' => true];
    }

    private function logRoyaltyNotHeld(Entity $contact, \StdClass $data, string $outcome): array
    {
        $label = $outcome === 'no_show' ? 'RI niet opgedaagd' : 'RI geannuleerd';
        $action = $data->cancellationAction ?? null;

        if ($action === 'reschedule_now') {
            $booking = $this->getBooking($data->calendarId ?? null, $data->selectedDate ?? null, $data->selectedTime ?? null);
            $this->assertCalendarType($booking['calendarId'], 'royalty');

            $this->markPlannedMeeting($contact, 'royalty', $outcome === 'no_show' ? 'Not Held' : 'Cancelled');
            $this->bookingService->createInternalMeeting(
                $booking['calendarId'],
                $contact,
                $booking['selectedDate'],
                $booking['selectedTime']
            );
            $this->addNote(
                $contact,
                "$label, opnieuw geboekt op {$booking['selectedDate']} om {$booking['selectedTime']}",
                $data->coachNote ?? null,
                'RI'
            );

            return ['success' => true];
        }

        if ($action !== 'cancel_stop') {
            throw new BadRequest('Kies of er een nieuwe RI ingepland wordt.');
        }

        $this->markPlannedMeeting($contact, 'royalty', $outcome === 'no_show' ? 'Not Held' : 'Cancelled');
        $contact->set('cRiStatus', '');
        $this->entityManager->saveEntity($contact);
        $this->addNote($contact, $label, $data->coachNote ?? null, 'RI');

        return ['success' => true];
    }

    private function closeHom(Entity $contact, string $meetingStatus): void
    {
        $this->markPlannedMeeting($contact, 'hom', $meetingStatus);

        $contact->set('cHomPlanned', false);
        $this->entityManager->saveEntity($contact);
    }

    private function markPlannedMeeting(Entity $contact, string $calendarType, string $status): void
    {
        $meetings = $this->entityManager->getRDBRepository('Meeting')
            ->where([
                'parentId' => $contact->getId(),
                'parentType' => 'Contact',
                'status' => ['Planned', 'Tentative'],
            ])
            ->order('dateStart', 'ASC')
            ->find();

        foreach ($meetings as $meeting) {
            $calendar = $meeting->get('cCalendarId')
                ? $this->entityManager->getEntityById('CCalendar', $meeting->get('cCalendarId'))
                : null;

            if ($calendar && $this->isCalendarType((string) $calendar->get('type'), $calendarType)) {
                $meeting->set('status', $status);
                $this->entityManager->saveEntity($meeting);

                return;
            }
        }
    }

    private function addNote(Entity $contact, string $text, ?string $coachNote, string $name = 'HOM'): void
    {
        if ($coachNote) {
            $text .= "\n" . $coachNote;
        }

        $note = $this->entityManager->getNewEntity('CNote');
        $note->set([
            'name' => $name,
            'note' => $text,
            'contactId' => $contact->getId(),
            'assignedUserId' => $contact->get('assignedUserId'),
        ]);
        $this->entityManager->saveEntity($note);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function getContact(\StdClass $data): Entity
    {
        if (empty($data->id)) {
            throw new BadRequest('Klant ID is verplicht.');
        }

        $contact = $this->entityManager->getEntityById('Contact', (string) $data->id);
        if (!$contact) {
            throw new NotFound('Klant niet gevonden.');
        }

        if (!$this->acl->checkEntityEdit($contact)) {
            throw new Forbidden('Geen bewerkrechten voor deze klant.');
        }

        return $contact;
    }

    /**
     * @throws BadRequest
     */
    private function getBooking(?string $calendarId, ?string $date, ?string $time, string $error = 'Kies een agenda en tijdstip.'): array
    {
        if (empty($calendarId) || empty($date) || empty($time)) {
            throw new BadRequest($error);
        }

        return ['calendarId' => $calendarId, 'selectedDate' => $date, 'selectedTime' => $time];
    }

    /**
     * @throws BadRequest
     */
    private function assertCalendarType(string $calendarIdentifier, string $expectedType): void
    {
        $calendarId = $this->slugService->resolve('CCalendar', $calendarIdentifier);
        $calendar = $calendarId ? $this->entityManager->getEntityById('CCalendar', $calendarId) : null;
        $type = $calendar ? (string) $calendar->get('type') : null;

        if ($type === null || !$this->isCalendarType($type, $expectedType)) {
            throw new BadRequest($expectedType === 'hom' ? 'Kies een HOM-agenda.' : 'Kies een Royalty (RI) agenda.');
        }
    }

    private function isCalendarType(string $type, string $expectedType): bool
    {
        // HOM calendars may still carry the legacy type 'iom'
        return $expectedType === 'hom'
            ? IntroMeetingType::fromCalendarType($type) === IntroMeetingType::HOM
            : $type === $expectedType;
    }
}
