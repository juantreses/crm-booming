<?php

namespace Espo\Modules\Booking\Services;

use DateTime;
use DateTimeZone;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Calendar\Services\CalendarService;
use Espo\Modules\LeadManager\Services\LeadService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

readonly class BookingService
{
    public function __construct(
        private EntityManager $entityManager,
        private CalendarService $calendarService,
        private LeadService $leadService,
        private SlugService $slug
    ) {}

    /**
     * @throws Conflict
     */
    public function processBooking(array $data): array
    {
        $calendarIdentifier = $data['calendarId'];
        $calendarId = $this->slug->resolve('CCalendar', $calendarIdentifier);
        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);

        $targetSlot = $this->findSlot($calendarId, $data['date'], $data['time']);

        $person = $this->leadService->findOrCreate($data);

        $dateStartString = $data['date'] . ' ' . $data['time'] .':00';
        $meeting = $this->createMeeting($calendar, $person, $dateStartString, $targetSlot, $data['note']);

        return ['success' => true, 'id' => $meeting->get('id')];
    }

    /**
     * Maakt direct een meeting aan vanuit interne logica (bvb Lead logCall)
     */
    public function createInternalMeeting(string $calendarIdentifier, Entity $person, string $date, string $time, ?string $notes = null): Entity
    {
        $calendarId = $this->slug->resolve('CCalendar', $calendarIdentifier);
        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
        
        if (!$calendar) {
            throw new NotFound("Kalender met ID '$calendarId' niet gevonden.");
        }

        $targetSlot = $this->findSlot($calendarId, $date, $time);

        $dateStartString = $date . ' ' . $time . ':00';

        return $this->createMeeting($calendar, $person, $dateStartString, $targetSlot, $notes);
    }

    /**
     * @throws Conflict
     */
    private function findSlot(string $calendarId, string $date, string $time): array
    {
        $slots = $this->calendarService->getAvailableSlots($calendarId, $date);
        $isStillAvailable = false;
        foreach ($slots as $slot) {
            if ($slot['start'] === $time && $slot['isBookable']) {
                $targetSlot = $slot;
                $isStillAvailable = true;
                break;
            }
        }

        if (!$isStillAvailable) {
            throw new Conflict("Helaas, tijdstip is niet (meer) beschikbaar.");
        }

        return $targetSlot;
    }

    private function createMeeting($calendar, $person, string $dateStartString, array $targetSlot, ?string $notes = null): Entity
    {
        $duration = $calendar->get('duration') ?? 60;

        $dateStart = new DateTime($dateStartString, new DateTimeZone('Europe/Brussels'));
        $dateStart->setTimezone(new DateTimeZone('UTC'));
        $dateEnd = (clone $dateStart)->modify("+$duration minutes");

        $meeting = $this->entityManager->getNewEntity('Meeting');
        $meeting->set([
            'name' => ucfirst($calendar->get('type')) . ' - ' . $person->get('name'),
            'status' => $calendar->get('needsApproval') ? 'Tentative' : 'Planned',
            'dateStart' => $dateStart->format('Y-m-d H:i:s'),
            'dateEnd' => $dateEnd->format('Y-m-d H:i:s'),
            'description' => $notes ?? '',
            'cCalendarId' => $calendar->get('id'),
            'parentId' => $person->get('id'),
            'parentType' => $person->getEntityType(),
            'assignedUserId' => $calendar->get('assignedUserId') ?: $person->get('assignedUserId'),
            'cLocationId' => $targetSlot['locationId'] ?? null,
        ]);

        $this->entityManager->saveEntity($meeting);

        $relationName = ($person->getEntityType() === 'Contact') ? 'contacts' : 'leads';
        $this->entityManager
            ->getRDBRepository('Meeting')
            ->getRelation($meeting, $relationName)
            ->relate($person, [
                'status' => 'Accepted'
            ]);

        $this->entityManager
            ->getRDBRepository('Meeting')
            ->getRelation($meeting, 'users')
            ->relateById($person->get('assignedUserId'));

        return $meeting;
    }

}