<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\ORM\EntityManager;

readonly class LeadCallCountService
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function increment(string $leadId): void
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            return;
        }
        
        $currentCount = $lead->get('cCallCount') ?? 0;
        $lead->set('cCallCount', $currentCount + 1);
        $this->entityManager->saveEntity($lead);
    }

    public function getCount(string $leadId): int
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            return 0;
        }
        
        return $lead->get('cCallCount') ?? 0;
    }
}