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
        $GLOBALS['log']->debug('Meeting before save');
        if (!$this->shouldAssignCalendar($entity)) {
            return;
        }

        $assignedUserId = $entity->get('assignedUserId');
        
        if (!$assignedUserId) {
            return;
        }

        $user = $this->entityManager->getEntityById('User', $assignedUserId);
        
        if (!$user) {
            return;
        }

        $defaultCalendarId = $user->get('cCalendarId');
        
        if (!$defaultCalendarId) {
            return;
        }

        $calendar = $this->entityManager->getEntityById('CCalendar', $defaultCalendarId);
        
        if (!$calendar || !$calendar->get('isActive')) {
            return;
        }

        $entity->set('cCalendarId', $defaultCalendarId);
    }

    /**
     * Determine if we should assign a calendar
     */
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