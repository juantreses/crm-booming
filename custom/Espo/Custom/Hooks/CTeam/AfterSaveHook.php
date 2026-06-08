<?php

namespace Espo\Custom\Hooks\CTeam;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly Log $log,
        private readonly GroupAssignmentService $groupAssignmentService,
    ) {}

    public function afterSave(Entity $cTeam, SaveOptions $options): void
    {
        if (!$cTeam->isAttributeChanged('sponserId')) {
            return;
        }

        try {
            $this->groupAssignmentService->syncLeadsAndContactsForTeamAndDownlines($cTeam);
        } catch (\Exception $e) {
            $this->log->error('CTeam After Save Hook error: ' . $e->getMessage());
        }
    }
}
