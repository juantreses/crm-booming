<?php

namespace Espo\Modules\Calendar\Hooks\Meeting;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Hook to automatically assign calendar to meetings based on assigned user's default calendar
 * Triggers on new meetings or when assignedUserId changes, only if no calendar is already set
 */
class AssignDefaultCalendar implements BeforeSave
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

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
        
        if (!$calendar) {
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

        if ($entity->isAttributeChanged('assignedUserId')) {
            return true;
        }

        return false;
    }
}