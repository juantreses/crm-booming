<?php

namespace Espo\Modules\LeadManager\Handlers\Call;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

class CalledHandler extends AbstractOutcomeHandler
{
    public function getEventTypes(): array
    {
        return [LeadEventType::CALLED];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'Telefoon',
            $context['eventDate'] ?? null
        );

        $this->followUpService->clearFollowUpAction($leadId);

        return $result;
    }
}