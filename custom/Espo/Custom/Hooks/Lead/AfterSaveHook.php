<?php

namespace Espo\Custom\Hooks\Lead;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\VankoWebhookService;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly Log $log,
        private readonly VankoWebhookService $vankoWebhookService,
    ) {}

    public function afterSave(Entity $lead, SaveOptions $options): void
    {
        try {
            $this->log->info('Lead After Save Hook triggered for Lead ID: ' . $lead->getId());
            if ($this->vankoWebhookService->hasLeadFieldsChanged($lead)) {
                $this->vankoWebhookService->syncAndProcessFromLead($lead);
            }
        } catch (\Exception $e) {
            $this->log->error('Lead After Save Hook error: ' . $e->getMessage());
        }
    }
}