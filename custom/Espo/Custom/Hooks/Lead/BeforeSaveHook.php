<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\ORM\Entity;
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
    ) {
    }

    public function beforeSave(Entity $lead, SaveOptions $options): void
    {
        try {
            $this->groupAssignmentService->syncGroupsFromFields($lead, self::FIELDS_TO_WATCH);
            if (
                $lead->isNew()
                || $lead->isAttributeChanged('cTeamId')
            ) {
                $this->groupAssignmentService->syncAssignedUserFromTeamFields($lead);
            }

            $this->syncDateAssigned($lead);
        } catch (\Exception $e) {
            $this->log->error('Lead Before Save Hook error: ' . $e->getMessage());
        }
    }

    private function syncDateAssigned(Entity $lead): void
    {
        if (!$lead->get('cTeamId')) {
            return;
        }

        if ($lead->isNew() || $lead->isAttributeChanged('cTeamId')) {
            $lead->set('cDateAssigned', gmdate('Y-m-d H:i:s'));
        }
    }
}
