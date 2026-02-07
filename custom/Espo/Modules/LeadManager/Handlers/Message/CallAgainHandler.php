<?php

namespace Espo\Modules\LeadManager\Handlers\Message;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

class CallAgainHandler extends AbstractOutcomeHandler
{
    public function getEventTypes(): array
    {
        return [LeadEventType::CALL_AGAIN];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId);
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'Bericht'
        );

        $this->handleFollowUp(
            $leadId,
            $context['callAgainDateTime'] ?? null
        );

        return $result;
    }
}