<?php

namespace Espo\Modules\LeadManager\Handlers\Message;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

class InvitedHandler extends AbstractOutcomeHandler
{
    public function __construct(
        LeadEventLogService $eventLogService,
        LeadNotesService $notesService,
        LeadFollowUpService $followUpService,
        private readonly LeadMeetingService $meetingService,
    ) {
        parent::__construct($eventLogService, $notesService, $followUpService);
    }

    public function getEventTypes(): array
    {
        return [LeadEventType::BOOK_INTRO];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'Bericht',
            $context['eventDate'] ?? null
        );

        $this->followUpService->clearFollowUpAction($leadId);

        $GLOBALS['log']->info('InvitedHandler: ' . json_encode($context));


        if (isset($context['calendarId'], $context['selectedDate'], $context['selectedTime'])) {
            $this->meetingService->createInternalMeetingForLeadId(
                $context['calendarId'],
                $leadId,
                $context['selectedDate'],
                $context['selectedTime'],
                $context['coachNote'] ?? null
            );
        }

        return $result;
    }
}