<?php

namespace Espo\Modules\Booking\Services;

use DateTime;
use DateTimeZone;
use Espo\Core\Exceptions\Conflict;
use Espo\Modules\Calendar\Services\CalendarService;
use Espo\Modules\LeadManager\Services\LeadService;
use Espo\Modules\Utils\SlugService;
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

        //--- RACE CONDITION CHECK ---
        $slots = $this->calendarService->getAvailableSlots($calendarId, $data['date']);
        $isStillAvailable = false;
        foreach ($slots as $slot) {
            if ($slot['start'] === $data['time'] && $slot['isBookable']) {
                $targetSlot = $slot;
                $isStillAvailable = true;
                break;
            }
        }

        if (!$isStillAvailable) {
            throw new Conflict("Helaas, dit tijdstip is zojuist volgeboekt. Kies een ander moment.");
        }

        $dateStartStr = $data['date'] . ' ' . $data['time'] . ':00';
        $duration = $calendar->get('duration') ?? 60;

        $dateStart = new DateTime($dateStartStr, new DateTimeZone('Europe/Brussels'));
        $dateStart->setTimezone(new DateTimeZone('UTC'));
        $dateEnd = (clone $dateStart)->modify("+$duration minutes");

        $status = $calendar->get('needsApproval') ? 'Tentative' : 'Planned';
        $person = $this->leadService->findOrCreate($data);

        $meeting = $this->entityManager->getNewEntity('Meeting');
        $meeting->set([
            'name' => ucfirst($calendar->get('type')) . ' - ' . $person->get('name'),
            'status' => $status,
            'dateStart' => $dateStart->format('Y-m-d H:i:s'),
            'dateEnd' => $dateEnd->format('Y-m-d H:i:s'),
            'description' => $data['note'] ?? '',
            'cCalendarId' => $calendar->get('id'),
            'parentId' => $person->get('id'),
            'parentType' => $person->getEntityType(),
            'assignedUserId' => $person->get('assignedUserId'),
            'cLocationStreet' => $targetSlot['locationAddressStreet'],
            'cLocationCity' => $targetSlot['locationAddressCity'],
            'cLocationState' => $targetSlot['locationAddressState'],
            'cLocationCountry' => $targetSlot['locationAddressCountry'],
            'cLocationPostalCode' => $targetSlot['locationAddressPostalCode'],
        ]);

        $this->entityManager->saveEntity($meeting);

        $relationName = ($person->getEntityType() === 'Contact') ? 'contacts' : 'leads';
        $this->entityManager
            ->getRDBRepository('Meeting')
            ->getRelation($meeting, $relationName)
            ->relate($person, [
                'status' => 'Accepted'
            ]);

        return ['success' => true, 'id' => $meeting->get('id')];
    }

}