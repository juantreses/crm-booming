<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Modules\Utils\DateTimeFactory;
use Espo\ORM\EntityManager;

readonly class LeadFollowUpService
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function addFollowUpAction(
        string $leadId,
        string $callAgainDateTime,
        string $followUpNote = 'Opnieuw bellen'
    ): void {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            return;
        }

        $dt = new \DateTime($callAgainDateTime);
        $formatted = DateTimeFactory::formatBrussels($dt);
        $line = "$followUpNote: $formatted";
        
        $lead->set('cFollowUpAction', $line);
        $this->entityManager->saveEntity($lead);
    }

    public function setFollowUpActionText(string $leadId, string $text): void
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            return;
        }

        $lead->set('cFollowUpAction', $text);
        $this->entityManager->saveEntity($lead);
    }

    public function clearFollowUpAction(string $leadId): void
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            return;
        }

        $lead->set('cFollowUpAction', '');
        $this->entityManager->saveEntity($lead);
    }
}
