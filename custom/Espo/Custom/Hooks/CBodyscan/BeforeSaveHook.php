<?php

namespace Espo\Custom\Hooks\CBodyscan;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

class BeforeSaveHook implements BeforeSave
{
    private const array FIELDS_TO_WATCH = [
        'cSlimFitCenter',
        'cTeam',
    ];
    public function __construct(
        private readonly Log $log,
        private readonly GroupAssignmentService $groupAssignmentService,
        private readonly EntityManager $entityManager,
    ) {
    }

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        try {
            $contact = $this->entityManager->getRelation($entity, 'contact')->findOne();

            $this->groupAssignmentService->syncGroupsFromFields(
                $entity,
                self::FIELDS_TO_WATCH,
                $contact,
                $this->groupAssignmentService->getCoachExtraGroupNamesFromContact($contact),
            );
        } catch (\Exception $e) {
            $this->log->error('Lead Before Save Hook error: ' . $e->getMessage());
        }
    }
}
