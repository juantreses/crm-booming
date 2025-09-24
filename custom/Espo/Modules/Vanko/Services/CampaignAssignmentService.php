<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;
use Espo\Modules\Vanko\Services\Util\EntityFactory;

/**
 * Handles assigning a Campaign to a Lead and applying RoundRobin logic.
 */
class CampaignAssignmentService
{
    private const ENTITY_CAMPAIGN = 'Campaign';
    private const ENTITY_CAMPAIGN_ROUNDROBIN = "CCampagneRoundRobin";
    private const ENTITY_LEAD = 'Lead';

    public function __construct(
        private readonly EntityManager $entityManager,
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
        $campaign = $this->entityFactory->findOrCreate(
            self::ENTITY_CAMPAIGN,
            $campaignName,
            ['status' => 'Active']
        );

        if ($campaign === null) {
            $this->log->error("Failed to find or create campaign '{$campaignName}' for lead {$lead->getId()}");
            return;
        }
        
        if ($lead->get('campaignId') === $campaign->getId()) {
            $this->log->info("Lead {$lead->getId()} is already assigned to campaign {$campaignName}.");
            $this->applyRoundRobinLogic($lead, $campaign);
            return;
        }

        $this->assignCampaign($lead, $campaign);
    }

    private function assignCampaign(Lead $lead, Entity $campaign): void
    {
        try {
            $this->entityManager->getRepository('Lead')->getRelation($lead, 'campaign')->relate($campaign);
            $this->log->info("Assigned Campaign {$campaign->getId()} to lead {$lead->getId()}");
            $this->applyRoundRobinLogic($lead, $campaign);
        } catch (\Exception $e) {
            $this->log->error("Failed to assign Campaign {$campaign->getId()} to lead {$lead->getId()}: " . $e->getMessage());
        }
    }

    private function applyRoundRobinLogic(Lead $lead, Entity $campaign): void
    {
        try {
            $campaignId = $campaign->getId();
            $centerId = $lead->get('cSlimFitCenterId');
            $teamId = $lead->get('cTeamId');
            if($teamId){
                $this->log->warning("Cannot apply RoundRobin for lead {$lead->getId()}: already has team: " . $teamId . ".");
                return;
            }
            if (!$campaignId || !$centerId) {
                $this->log->warning("Cannot apply RoundRobin for lead {$lead->getId()}: missing Campaign or SlimFitCenter ID.");
                return;
            }

            $roundRobin = $this->entityManager->getRepository(self::ENTITY_CAMPAIGN_ROUNDROBIN)
                ->where(['campaignId' => $campaignId, 'slimFitCenterId' => $centerId])
                ->findOne();

            if (!$roundRobin || !$roundRobin->get('roundRobinActief')) {
                return;
            }

            $activeMembersIds = $roundRobin->getLinkMultipleIdList('activeMembers') ?? [];
            if (empty($activeMembersIds)) {
                $this->log->warning("RoundRobin {$roundRobin->getId()} is active but has no members.");
                return;
            }

            $memberLeadCounts = [];
            foreach ($activeMembersIds as $memberId) {
                $count = $this->entityManager->getRepository(self::ENTITY_LEAD)
                    ->where([
                        'cCampagneRoundRobinId' => $roundRobin->getId(),
                        'cTeamId' => $memberId,
                        'createdAt >=' => $roundRobin->get('roundRobinStart')
                    ])
                    ->count();
                $memberLeadCounts[$memberId] = $count;
            }

            asort($memberLeadCounts);
            $memberToAssignId = key($memberLeadCounts);
            
            $this->log->info("RoundRobin determined that member (cTeam) {$memberToAssignId} should be assigned to lead {$lead->getId()}.");
            
        } catch (\Exception $e) {
            $this->log->error("Failed to apply RoundRobin logic for Campaign {$campaign->getId()} to lead {$lead->getId()}: " . $e->getMessage());
        }
    }
}