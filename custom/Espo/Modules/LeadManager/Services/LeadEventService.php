<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\ORM\EntityManager;
use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\ValueObjects\CallOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\KickstartFollowUpOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\KickstartOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\MessageOutcomeData;

readonly class LeadEventService
{
    public function __construct(
        private EntityManager $entityManager,
        private LeadEventLogService $eventLogService,
        private LeadCallCountService $callCountService,
        private HandlerRegistry $handlerRegistry,
    ) {}

    public function logEvent(
        string $leadId,
        LeadEventType $eventType,
        ?string $eventDate = null,
        ?string $description = null
    ): array {
        return $this->eventLogService->logEvent($leadId, $eventType, $eventDate, $description);
    }

    public function logCall(\StdClass $data): array
    {
        $callData = CallOutcomeData::fromStdClass($data);
        
        $this->callCountService->increment($callData->leadId);

        $handler = $this->handlerRegistry->getCallHandler($callData->outcome->value);
        
        $context = [
            'eventDate' => $callData->callDateTime,
            'callAgainDateTime' => $callData->callAgainDateTime,
            'coachNote' => $callData->coachNote,
            'calendarId' => $callData->calendarId,
            'selectedDate' => $callData->selectedDate,
            'selectedTime' => $callData->selectedTime,
        ];

        $result = $handler->handle($callData->leadId, $context);
        
        return $result->toArray();
    }

    public function logKickstart(\StdClass $data): array
    {
        $kickstartData = KickstartOutcomeData::fromStdClass($data);
        
        $handler = $this->handlerRegistry->getKickstartHandler($kickstartData->outcome->value);
        
        $context = [
            'eventDate' => $kickstartData->kickstartDateTime,
            'callAgainDateTime' => $kickstartData->callAgainDateTime,
            'coachNote' => $kickstartData->coachNote,
            'cancellationAction' => $kickstartData->cancellationAction,
            'calendarId' => $kickstartData->calendarId,
            'selectedDate' => $kickstartData->selectedDate,
            'selectedTime' => $kickstartData->selectedTime,
        ];

        $result = $handler->handle($kickstartData->leadId, $context);
        
        return $result->toArray();
    }

    public function logKickstartFollowUp(\StdClass $data): array
    {
        $kickstartFollowUpData = KickstartFollowUpOutcomeData::fromStdClass($data);

        $handler = $this->handlerRegistry->getKickstartFollowUpHandler($kickstartFollowUpData->outcome->value);

        $context = [
            'eventDate' => $kickstartFollowUpData->followUpDateTime,
            'coachNote' => $kickstartFollowUpData->coachNote,
        ];
        
        $result = $handler->handle($kickstartFollowUpData->leadId, $context);
        
        return $result->toArray();
    }

    public function logMessageSent(\StdClass $data): array
    {
        $leadId = (string) $data->id;
        return $this->logEvent($leadId, LeadEventType::MESSAGE_SENT);
    }

    public function logMessageOutcome(\StdClass $data): array
    {
        $messageData = MessageOutcomeData::fromStdClass($data);
        
        $handler = $this->handlerRegistry->getMessageHandler($messageData->outcome->value);
        
        $context = [
            'callAgainDateTime' => $messageData->callAgainDateTime,
            'coachNote' => $messageData->coachNote,
            'calendarId' => $messageData->calendarId,
            'selectedDate' => $messageData->selectedDate,
            'selectedTime' => $messageData->selectedTime,
        ];

        $result = $handler->handle($messageData->leadId, $context);

        if ($messageData->outcome->value === 'Invited' && $messageData->meetingType) {
            $lead = $this->entityManager->getEntityById('Lead', $messageData->leadId);
            if ($lead) {
                $lead->set('cMeetingType', $messageData->meetingType);
                $this->entityManager->saveEntity($lead);
            }
        }

        return $result->toArray();
    }
}