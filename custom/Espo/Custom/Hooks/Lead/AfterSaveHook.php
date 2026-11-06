<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\EntitySyncService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly Log $log,
        private readonly EntitySyncService $entitySyncService,
    ) {
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        try {
            $this->entitySyncService->syncFromLead($entity);
        } catch (\Exception $e) {
            $this->log->error('Lead After Save Hook error: ' . $e->getMessage());
        }
    }
}
