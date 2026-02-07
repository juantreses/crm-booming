<?php

namespace Espo\Modules\LeadManager\Handlers;

use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

abstract class AbstractOutcomeHandler implements OutcomeHandler
{
    public function __construct(
        protected readonly LeadEventLogService $eventLogService,
        protected readonly LeadNotesService $notesService,
        protected readonly LeadFollowUpService $followUpService,
    ) {}

    protected function logEvents(string $leadId, ?string $eventDate = null): OutcomeResult
    {
        $result = new OutcomeResult();
        
        foreach ($this->getEventTypes() as $eventType) {
            $eventId = $this->eventLogService->logEvent($leadId, $eventType, $eventDate)['eventId'];
            $result = $result->addEventId($eventId);
        }

        return $result;
    }

    protected function addCoachNoteIfProvided(
        string $leadId,
        ?string $coachNote,
        string $source,
        ?string $eventDate = null
    ): void {
        if ($coachNote) {
            $this->notesService->addCoachNote($leadId, $coachNote, $source, $eventDate);
        }
    }

    protected function handleFollowUp(
        string $leadId,
        ?string $callAgainDateTime,
        ?string $followUpNote = null
    ): void {
        if ($callAgainDateTime) {
            $this->followUpService->addFollowUpAction(
                $leadId,
                $callAgainDateTime,
                $followUpNote ?? 'Opnieuw bellen'
            );
        } else {
            $this->followUpService->clearFollowUpAction($leadId);
        }
    }
}