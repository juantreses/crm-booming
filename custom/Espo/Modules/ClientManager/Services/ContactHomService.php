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
 * C/D book a Royalty (RI) meeting right away.
 */
readonly class ContactHomService
{
    /** Client types a client can switch to with outcome B */
    public const UPGRADE_TYPES = ['pcx_15', 'distributor'];

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
                ' spaarkaartpunten, en niet voor PCX of distributeurs.'
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

        if ($rating === HomRating::A && (int) $contact->get('cSavingsPointsBalance') < SavingsCardService::HOM_POINTS) {
            throw new BadRequest('Onvoldoende spaarkaartpunten (minimum ' . SavingsCardService::HOM_POINTS . ').');
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

            $this->markPlannedHom($contact, 'Cancelled');
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

    private function closeHom(Entity $contact, string $meetingStatus): void
    {
        $this->markPlannedHom($contact, $meetingStatus);

        $contact->set('cHomPlanned', false);
        $this->entityManager->saveEntity($contact);
    }

    private function markPlannedHom(Entity $contact, string $status): void
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

            if ($calendar && IntroMeetingType::fromCalendarType((string) $calendar->get('type')) === IntroMeetingType::HOM) {
                $meeting->set('status', $status);
                $this->entityManager->saveEntity($meeting);

                return;
            }
        }
    }

    private function addNote(Entity $contact, string $text, ?string $coachNote): void
    {
        if ($coachNote) {
            $text .= "\n" . $coachNote;
        }

        $note = $this->entityManager->getNewEntity('CNote');
        $note->set([
            'name' => 'HOM',
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

        $matches = $expectedType === 'hom'
            ? $type !== null && IntroMeetingType::fromCalendarType($type) === IntroMeetingType::HOM
            : $type === $expectedType;

        if (!$matches) {
            throw new BadRequest($expectedType === 'hom' ? 'Kies een HOM-agenda.' : 'Kies een Royalty (RI) agenda.');
        }
    }
}
