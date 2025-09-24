<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services;

use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;
use Espo\Modules\Vanko\Services\Util\EntityFactory;

/**
 * Handles assigning a CSlimFitCenter to a Lead.
 */
class SlimFitCenterAssignmentService
{
    private const ENTITY_SLIMFIT_CENTER = 'CSlimFitCenter';

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
        private readonly EntityFactory $entityFactory,
    ) {}

    /**
     * Assigns a CSlimFitCenter to a lead based on the center's name.
     * If the name is empty, it un-assigns any existing center.
     */
    public function assignCenterByName(Lead $lead, string $centerName): bool
    {
        if ($centerName === '') {
            $this->unassignCenter($lead);
            return false;
        }

        $this->log->info("Processing SlimFitCenter assignment for lead {$lead->getId()} with center: {$centerName}");
        $center = $this->entityFactory->findOrCreate(self::ENTITY_SLIMFIT_CENTER, $centerName);

        if ($center === null) {
            $this->log->error("Could not find or create center '{$centerName}' for lead {$lead->getId()}.");
            return false;
        }

        if ($lead->get('cSlimFitCenterId') === $center->getId()) {
            $this->log->info("Lead {$lead->getId()} is already assigned to center {$centerName}.");
            return true;
        }

        return $this->assignCenter($lead, $center);
    }

    private function assignCenter(Lead $lead, Entity $center): bool
    {
        try {
            $this->entityManager->getRepository('Lead')->getRelation($lead, 'cSlimFitCenter')->relate($center);
            $this->log->info("Assigned SlimFitCenter {$center->getId()} to lead {$lead->getId()}");
            return true;
        } catch (\Exception $e) {
            $this->log->error("Failed to assign SlimFitCenter {$center->getId()} to lead {$lead->getId()}: " . $e->getMessage());
            return false;
        }
    }

    public function unassignCenter(Lead $lead): void
    {
        if (empty($lead->get('cSlimFitCenterId'))) {
            return;
        }
        
        try {
            $this->entityManager->getRepository('Lead')->getRelation($lead, 'cSlimFitCenter')->unrelate($lead->get('cSlimFitCenter'));
            $this->log->info("Removed SlimFitCenter relationship from lead {$lead->getId()}");
        } catch (\Exception $e) {
            $this->log->error("Failed to remove SlimFitCenter relationship from lead {$lead->getId()}: " . $e->getMessage());
        }
    }
}