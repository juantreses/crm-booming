<?php

namespace Espo\Modules\LeadManager\Handlers\IntroMeeting;

use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\IntroMeetingService;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\ORM\EntityManager;


class NoShowHandler extends AbstractOutcomeHandler
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
        return [LeadEventType::INTRO_NO_SHOW, LeadEventType::CALL_AGAIN];
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

        $remaining = $this->introMeetingService->getRemainingUsage($lead, $meetingType);

        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);
        $noteText = "{$meetingType->value} no-show";
        
        if ($remaining > 0 && $meetingType->hasUsageLimit()) {
            $noteText .= ". Nog {$remaining} sessie(s) beschikbaar voor herboeking";
        }
        
        if ($context['coachNote'] ?? null) {
            $noteText .= "\n" . $context['coachNote'];
        }

        $this->addCoachNoteIfProvided(
            $leadId,
            $noteText,
            'Afspraak',
            $context['eventDate'] ?? null
        );

        $lead->set('cStage', LeadStage::FOLLOW_UP->value);
        $this->entityManager->saveEntity($lead);

        $this->handleFollowUp(
            $leadId,
            $context['callAgainDateTime'] ?? null,
            "Niet opgedaagd {$meetingType->value} - Opnieuw boeken"
        );

        $meeting = $this->meetingService->findPlannedMeeting($leadId);
        if ($meeting) {
            $this->meetingService->markAsNotHeld($meeting);
        }

        return $result;
    }
}
