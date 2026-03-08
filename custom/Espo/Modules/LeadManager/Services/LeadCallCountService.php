<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\ORM\EntityManager;

readonly class LeadCallCountService
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function getCount(string $leadId): int
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        
        if (!$lead) {
            return 0;
        }
        
        return (int) ($lead->get('cCallCount') ?? 0);
    }

    public function increment(string $leadId): int
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        
        if (!$lead) {
            throw new \RuntimeException("Lead not found: {$leadId}");
        }
        
        $currentCount = (int) ($lead->get('cCallCount') ?? 0);
        $newCount = $currentCount + 1;
        
        $lead->set('cCallCount', $newCount);
        $this->entityManager->saveEntity($lead);
        
        return $newCount;
    }

    public function reset(string $leadId): void
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        
        if (!$lead) {
            return;
        }
        
        $lead->set('cCallCount', 0);
        $this->entityManager->saveEntity($lead);
    }

    public function hasReachedMax(string $leadId, int $maxAttempts = 3): bool
    {
        return $this->getCount($leadId) >= $maxAttempts;
    }
}