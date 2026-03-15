<?php

namespace Espo\Modules\LeadManager\Handlers\IntroMeeting;

use Espo\Custom\Enums\IntroMeetingType;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\IntroMeetingService;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CancelledHandler extends AbstractOutcomeHandler
{
    public function __construct(
        LeadEventLogService $eventLogService,
        LeadNotesService $notesService,
        LeadFollowUpService $followUpService,
        private readonly EntityManager $entityManager,
        private readonly IntroMeetingService $introMeetingService,
        private readonly LeadMeetingService $meetingService,
    ) {
        parent::__construct($eventLogService, $notesService, $followUpService);
    }

    public function getEventTypes(): array
    {
        return [LeadEventType::INTRO_CANCELLED];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            throw new \RuntimeException("Lead not found: {$leadId}");
        }

        $meetingType = $this->introMeetingService->getIntroMeetingType($lead);
        
        if (!$meetingType) {
            throw new \RuntimeException("Lead does not have an intro meeting type set");
        }

        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);
        $noteText = "{$meetingType->value} geannuleerd";
        
        if ($context['coachNote'] ?? null) {
            $noteText .= "\n" . $context['coachNote'];
        }
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $noteText,
            'Afspraak',
            $context['eventDate'] ?? null
        );

        $meeting = $this->meetingService->findPlannedMeeting($leadId);
        if ($meeting) {
            $this->meetingService->markAsCancelled($meeting);
        }

        $cancellationAction = $context['cancellationAction'] ?? 'cancel_stop';
        
        switch ($cancellationAction) {
            case 'reschedule_now':
                return $this->handleRescheduleNow($lead, $context, $result);
                
            case 'reschedule_later':
                return $this->handleRescheduleLater($lead, $context, $result, $meetingType);
                
            case 'cancel_stop':
            default:
                return $this->handleCancelStop($lead, $meetingType, $result);
        }
    }

    /**
     * Handle immediate rescheduling
     */
    private function handleRescheduleNow(Entity $lead, array $context, OutcomeResult $result): OutcomeResult
    {
        if (empty($context['calendarId']) || empty($context['selectedDate']) || empty($context['selectedTime'])) {
            throw new \InvalidArgumentException("Calendar ID, date, and time required for rescheduling");
        }

        $calendar = $this->entityManager->getEntityById('CCalendar', $context['calendarId']);
        if (!$calendar) {
            throw new \RuntimeException("Calendar not found: {$context['calendarId']}");
        }

        $calendarType = $calendar->get('type');
        $nextMeetingType = IntroMeetingType::fromCalendarType($calendarType);
        if (!$nextMeetingType) {
            throw new \InvalidArgumentException("Calendar type '{$calendarType}' is not an intro meeting");
        }

        if (!$this->introMeetingService->canBook($lead, $nextMeetingType)) {
            throw new \RuntimeException("Lead cannot book {$nextMeetingType->value}");
        }

        $this->meetingService->createInternalMeeting(
            $context['calendarId'],
            $lead,
            $context['selectedDate'],
            $context['selectedTime'],
            null
        );

        $eventId = $this->eventLogService->logEvent(
            $lead->getId(),
            LeadEventType::BOOK_INTRO,
            $context['eventDate'] ?? null
        )['eventId'];
        
        $result = $result->addEventId($eventId);

        $this->addCoachNoteIfProvided(
            $lead->getId(),
            "{$nextMeetingType->value} opnieuw geboekt voor {$context['selectedDate']} om {$context['selectedTime']}",
            'Afspraak',
            $context['eventDate'] ?? null
        );

        $lead->set('cStage', LeadStage::INTRO_SCHEDULED->value);
        $lead->set('cMeetingType', $nextMeetingType->value);
        $this->entityManager->saveEntity($lead);

        $this->followUpService->clearFollowUpAction($lead->getId());

        return $result;
    }

    private function handleRescheduleLater(Entity $lead, array $context, OutcomeResult $result, IntroMeetingType $meetingType): OutcomeResult
    {
        $eventId = $this->eventLogService->logEvent(
            $lead->getId(),
            LeadEventType::CALL_AGAIN,
            $context['eventDate'] ?? null
        )['eventId'];
        
        $result = $result->addEventId($eventId);

        $lead->set('cStage', LeadStage::FOLLOW_UP->value);
        $this->entityManager->saveEntity($lead);

        $this->handleFollowUp(
            $lead->getId(),
            $context['callAgainDateTime'] ?? null,
            "{$meetingType->value} geannuleerd - Opnieuw inplannen"
        );

        return $result;
    }

    private function handleCancelStop(Entity $lead, IntroMeetingType $meetingType, OutcomeResult $result): OutcomeResult
    {
        $lead->set('cStage', LeadStage::FOLLOW_UP->value);
        $this->entityManager->saveEntity($lead);

        $this->followUpService->addFollowUpAction(
            $lead->getId(),
            "{$meetingType->value} geannuleerd - Contact opnemen"
        );

        return $result;
    }
}
