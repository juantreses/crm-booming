<?php

namespace Espo\Modules\Calendar\Hooks\Meeting;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Gives a meeting the calendar of its assigned user. Calendars are assigned to sync users
 * (e.g. the "sync kickstart" user has the kickstart calendar), so a meeting assigned to such a user
 * lands in that calendar and is synced to its Google calendar.
 * Runs for new meetings, meetings without calendar and when the assigned user changes,
 * also when the meeting already has a calendar.
 */
class AssignDefaultCalendar implements BeforeSave
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {
    }

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$this->shouldAssignCalendar($entity)) {
            return;
        }

        $assignedUserId = $entity->get('assignedUserId');

        if (!$assignedUserId) {
            return;
        }

        $calendar = $this->entityManager
            ->getRDBRepository('CCalendar')
            ->where([
                'assignedUserId' => $assignedUserId,
                'isActive' => true,
            ])
            ->findOne();

        if (!$calendar instanceof Entity) {
            return;
        }

        $entity->set('cCalendarId', $calendar->getId());
    }

    private function shouldAssignCalendar(Entity $entity): bool
    {
        if (! $entity->get('cCalendarId')) {
            return true;
        }

        if ($entity->isNew()) {
            return true;
        }
        return $entity->isAttributeChanged('assignedUserId');
    }
}
