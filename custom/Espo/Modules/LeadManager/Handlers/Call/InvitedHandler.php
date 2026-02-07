<?php

namespace Espo\Modules\LeadManager\Handlers\Call;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\ORM\EntityManager;

class InvitedHandler extends AbstractOutcomeHandler
{
    public function __construct(
        LeadEventLogService $eventLogService,
        LeadNotesService $notesService,
        LeadFollowUpService $followUpService,
        private readonly LeadMeetingService $meetingService,
        private readonly EntityManager $entityManager,
    ) {
        parent::__construct($eventLogService, $notesService, $followUpService);
    }

    public function getEventTypes(): array
    {
        return [LeadEventType::CALLED, LeadEventType::APPOINTMENT_BOOKED];
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

        if (isset($context['calendarId'], $context['selectedDate'], $context['selectedTime'])) {
            $lead = $this->entityManager->getEntityById('Lead', $leadId);
            if ($lead) {
                $this->meetingService->createInternalMeeting(
                    $context['calendarId'],
                    $lead,
                    $context['selectedDate'],
                    $context['selectedTime'],
                    $context['coachNote'] ?? null
                );
            }
        }

        return $result;
    }
}