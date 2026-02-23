<?php

namespace Espo\Modules\Booking\Hooks\Meeting;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

class GroupAssignment implements BeforeSave
{

    private const FIELDS_TO_WATCH = [
        'cTeam',
    ];

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly GroupAssignmentService $groupAssignmentService,
    ) {}

    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (! $entity->get('parentType') || ! $entity->get('parentId')) {
            return;
        }
        $person = $this->entityManager->getEntityById($entity->get('parentType'), $entity->get('parentId'));
        if (!$person) {
            return;
        }
        try {
            $this->groupAssignmentService->syncGroupsFromFields($entity, self::FIELDS_TO_WATCH, $person);
        } catch (\Exception $e) {
            $GLOBALS['log' ]->error('Meeting Before Save Hook error: ' . $e->getMessage());
        }
    }
}