<?php

namespace Espo\Modules\Calendar\Services;

use DateTime;
use Espo\ORM\EntityManager;

readonly class BookingService
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function processBooking(array $data): array
    {
        $calendar = $this->entityManager->getEntityById('CCalendar', $data['calendarId']);

        $dateStartStr = $data['date'] . ' ' . $data['time'] . ':00';
        $duration = $calendar->get('duration') ?? 60;

        $dateStart = new DateTime($dateStartStr);
        $dateEnd = (clone $dateStart)->modify("+$duration minutes");

        $status = $calendar->get('needsApproval') ? 'Tentative' : 'Planned';
        $person = $this->findOrCreatePerson($data);

        $meeting = $this->entityManager->getNewEntity('Meeting');
        $meeting->set([
            'name' => $calendar->get('name') . ' - ' . $person->get('name'),
            'status' => $status,
            'dateStart' => $dateStart->format('Y-m-d H:i:s'),
            'dateEnd' => $dateEnd->format('Y-m-d H:i:s'),
            'description' => $data['note'] ?? '',
            'CCalendarId' => $calendar->get('id'),
            'CType' => $calendar->get('cType'),
            //'confirmationEmailTemplateId' => $calendar->get('confirmationTemplateId')
        ]);

        $this->entityManager->saveEntity($meeting);

        $this->entityManager->getRDBRepository('Meeting')->getRelation($meeting, 'user')->relate($person);

        return ['success' => true, 'id' => $meeting->get('id')];
    }

    private function findOrCreatePerson(array $data)
    {
    }
}