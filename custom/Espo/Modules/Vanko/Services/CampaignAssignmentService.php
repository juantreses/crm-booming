<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;
use Espo\Core\Utils\Log;
use Espo\Modules\Vanko\Services\Util\EntityFactory;

/**
 * Handles assigning a Campaign to a Lead and applying RoundRobin logic.
 */
class CampaignAssignmentService
{
    private const ENTITY_CAMPAIGN = 'Campaign';

    public function __construct(
        private readonly Log $log,
        private readonly EntityFactory $entityFactory,
    ) {}

    public function assignCampaignByName(Lead $lead, string $campaignName): void
    {
        if ($campaignName === '') {
            $this->log->info("No campaign name provided for lead {$lead->getId()}, skipping assignment.");
            return;
        }
        $this->log->info("Processing Campaign assignment for lead {$lead->getId()} with campaign: {$campaignName}");
        $campaign = $this->findOrCreateCampaign($lead, $campaignName);
        if ($campaign === null) {
            return; // Error already logged inside findOrCreateCampaign
        }
        if ($lead->get('campaignId') !== $campaign->getId()) {
            $this->assignCampaign($lead, $campaign);
        } else {
            $this->log->info("Lead {$lead->getId()} is already assigned to campaign {$campaignName}.");
        }
    }

    private function findOrCreateCampaign(Lead $lead, string $campaignName): ?Entity
    {
        $campaign = $this->entityFactory->findOrCreate(
            self::ENTITY_CAMPAIGN,
            $campaignName,
            ['status' => 'Active']
        );

        if ($campaign === null) {
            $this->log->error("Failed to find or create campaign '{$campaignName}' for lead {$lead->getId()}");
        }
        return $campaign;
    }

    private function assignCampaign(Lead $lead, Entity $campaign): void
    {
        try {
            $lead->set('campaignId',$campaign->getId());
            $this->log->info("Assigned Campaign {$campaign->getId()} to lead {$lead->getId()}");
        } catch (\Exception $e) {
            $this->log->error("Failed to assign Campaign {$campaign->getId()} to lead {$lead->getId()}: " . $e->getMessage());
        }
    }
}