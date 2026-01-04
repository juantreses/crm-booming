<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\EntitySyncService;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly Log $log,
        private readonly EntitySyncService $entitySyncService,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        try {
            $this->log->info('Lead After Save Hook triggered for Lead ID: ' . $entity->getId());
            $this->entitySyncService->syncFromLead($entity);
        } catch (\Exception $e) {
            $this->log->error('Lead After Save Hook error: ' . $e->getMessage());
        }
    }
}