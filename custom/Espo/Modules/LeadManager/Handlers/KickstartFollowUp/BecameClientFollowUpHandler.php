<?php

namespace Espo\Modules\LeadManager\Handlers\KickstartFollowUp;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

class BecameClientFollowUpHandler extends AbstractOutcomeHandler
{
    protected const NOTE_SOURCE = 'KS - Opvolging';

    public function getEventTypes(): array
    {
        return [LeadEventType::BECAME_CLIENT];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            static::NOTE_SOURCE,
            $context['eventDate'] ?? null
        );

        $this->followUpService->clearFollowUpAction($leadId);

        return $result;
    }
}