<?php

namespace Espo\Modules\LeadManager\Handlers\Message;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

class InvitedHandler extends AbstractOutcomeHandler
{
    public function getEventTypes(): array
    {
        return [LeadEventType::BOOK_INTRO];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId);
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'Bericht'
        );

        $this->followUpService->clearFollowUpAction($leadId);

        return $result;
    }
}