<?php

namespace Espo\Custom\Hooks\Contact;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\EntitySyncService;
use Espo\Custom\Services\GroupAssignmentService;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly Log $log,
        private readonly EntitySyncService $entitySyncService,
        private readonly GroupAssignmentService $groupAssignmentService,
    ) {}

    public function afterSave(Entity $contact, SaveOptions $options): void
    {
        try {
            $this->entitySyncService->syncFromContact($contact);

            if ($this->shouldSyncRelatedTeams($contact)) {
                $this->groupAssignmentService->syncRelatedTeamsFromContact($contact);
            }
        } catch (\Exception $e) {
            $this->log->error('Contact After Save Hook error: ' . $e->getMessage());
        }
    }

    private function shouldSyncRelatedTeams(Entity $contact): bool
    {
        return $contact->isAttributeChanged('cTeamId')
            || $contact->isAttributeChanged('cSlimFitCenterId')
            || $contact->isAttributeChanged('firstName')
            || $contact->isAttributeChanged('lastName');
    }
}