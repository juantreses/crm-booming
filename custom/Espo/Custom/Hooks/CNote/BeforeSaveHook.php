<?php

namespace Espo\Custom\Hooks\CNote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\ORM\EntityManager;

class BeforeSaveHook implements BeforeSave
{
    private const FIELDS_TO_WATCH = [
        'cSlimFitCenter',
        'cTeam',
    ];
    public function __construct(
        private readonly Log $log,
        private readonly GroupAssignmentService $groupAssignmentService,
        private readonly EntityManager $entityManager,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        try {
            $contact = $this->entityManager->getRelation($entity, 'contact')->findOne();
            $this->groupAssignmentService->syncGroupsFromFields($entity, self::FIELDS_TO_WATCH, $contact);
        } catch (\Exception $e) {
            $this->log->error('Lead Before Save Hook error: ' . $e->getMessage());
        }
    }
}