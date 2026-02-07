<?php

namespace Espo\Modules\LeadManager\Handlers\KickstartFollowUp;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

class NotConvertedFollowUpHandler extends AbstractOutcomeHandler
{
    public function getEventTypes(): array
    {
        return [LeadEventType::NOT_CONVERTED];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'KS - Opvolging',
            $context['eventDate'] ?? null
        );

        $this->followUpService->clearFollowUpAction($leadId);

        return $result;
    }
}