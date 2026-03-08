<?php

namespace Espo\Modules\LeadManager\Handlers\Kickstart;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\ORM\EntityManager;

class CancelledHandler extends AbstractOutcomeHandler
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
        return [LeadEventType::KICKSTART_CANCELLED];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'Kickstart',
            $context['eventDate'] ?? null
        );

        $meeting = $this->meetingService->findPlannedMeeting($leadId);
        $this->meetingService->markAsCancelled($meeting);

        $cancellationAction = $context['cancellationAction'] ?? null;
        
        switch ($cancellationAction) {
            case 'reschedule_now':
                if (!empty($context['calendarId']) && !empty($context['selectedDate'])) {
                    $lead = $this->entityManager->getEntityById('Lead', $leadId);
                    if ($lead) {
                        $this->meetingService->createInternalMeeting(
                            $context['calendarId'],
                            $lead,
                            $context['selectedDate'],
                            $context['selectedTime'] ?? '',
                            $context['coachNote'] ?? null
                        );

                        $eventId = $this->eventLogService->logEvent(
                            $leadId,
                            LeadEventType::KICKSTART_BOOKED,
                            $context['eventDate'] ?? null
                        )['eventId'];
                        $result = $result->addEventId($eventId);
                        
                        $this->followUpService->clearFollowUpAction($leadId);
                    }
                }
                break;

            case 'reschedule_later':
                $eventId = $this->eventLogService->logEvent(
                    $leadId,
                    LeadEventType::CALL_AGAIN,
                    $context['eventDate'] ?? null
                )['eventId'];
                $result = $result->addEventId($eventId);
                
                $this->handleFollowUp(
                    $leadId,
                    $context['callAgainDateTime'] ?? null,
                    'Kickstart geannuleerd - Opnieuw inplannen'
                );
                break;

            case 'cancel_stop':
                // No additional action needed
                break;
        }

        return $result;
    }
}