<?php

declare(strict_types=1);

namespace Espo\Custom\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use StdClass;
readonly class AppointmentService
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function createAppointment(Entity $lead, StdClass $data): Entity
    {
        $appointment = $this->entityManager->getNewEntity('CVankoAppointment');

        $calendarName = $data->calenderName ?? '';
        $meetingType = $this->parseMeetingType($calendarName);

        $appointment->set([
            'vankoMeetingId'=> $data->vankoMeetingId ?? null,
            'datum'=> $data->startDate ?? null,
            'meeting_type' => $meetingType,
            'calendarName' => $calendarName,
            'leadId' => $lead->getId(),
            'status' => 'Planned'
        ]);

        $this->entityManager->saveEntity($appointment);

        return $appointment;
    }

    private function parseMeetingType(string $calendarName): ?string
    {
        if (empty($calendarName)) {
            return null;
        }

        $parts = explode(' - ', $calendarName);
        return trim(end($parts));
    }

    /**
     * @throws NotFound
     */
    public function cancelAppointment(Entity $lead, string $vankoId): bool
    {
        $appointment = $this->entityManager
            ->getRepository('CVankoAppointment')
            ->where([
                'vankoMeetingId' => $vankoId,
                'leadId' => $lead->getId(),
            ])
            ->findOne();

        if (!$appointment) {
            // Log a warning so you can still track that it was missing
            $GLOBALS['log']->warning("Vanko: Cancel requested for missing Meeting ID $vankoId");
            return false;
        }

        $appointment->set('status', 'Cancelled');
        $this->entityManager->saveEntity($appointment);

        return true;
    }
}