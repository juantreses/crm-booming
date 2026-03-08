<?php

namespace Espo\Modules\LeadManager\Handlers\IntroMeeting;

use Espo\Custom\Enums\IntroMeetingType;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\IntroMeetingService;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\EntityManager;

class InvitedHandler extends AbstractOutcomeHandler
{
    public function __construct(
        LeadEventLogService $eventLogService,
        LeadNotesService $notesService,
        LeadFollowUpService $followUpService,
        private readonly LeadMeetingService $meetingService,
        private readonly EntityManager $entityManager,
        private readonly IntroMeetingService $introMeetingService,
        private readonly SlugService $slugService,
    ) {
        parent::__construct($eventLogService, $notesService, $followUpService);
    }

    public function getEventTypes(): array
    {
        return [LeadEventType::BOOK_INTRO];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        if (!isset($context['calendarId'], $context['selectedDate'], $context['selectedTime'])) {
            throw new \InvalidArgumentException("Calendar ID, date, and time are required for booking");
        }

        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            throw new \RuntimeException("Lead not found: {$leadId}");
        }

        $calendarId = $this->slugService->resolve('CCalendar', $context['calendarId']);
        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
        
        if (!$calendar) {
            throw new \RuntimeException("Calendar not found: {$context['calendarId']}");
        }

        $calendarType = $calendar->get('type');
        $meetingType = IntroMeetingType::fromCalendarType($calendarType);
        
        if (!$meetingType) {
            throw new \InvalidArgumentException("Calendar type '{$calendarType}' is not an intro meeting");
        }

        if (!$this->introMeetingService->canBook($lead, $meetingType)) {
            $remaining = $this->introMeetingService->getRemainingUsage($lead, $meetingType);
            throw new \RuntimeException("Lead cannot book {$meetingType->value}");
        }

        $this->meetingService->createInternalMeeting(
            $context['calendarId'],
            $lead,
            $context['selectedDate'],
            $context['selectedTime'],
            $context['coachNote'] ?? null
        );

        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);

        $usageCount = $this->introMeetingService->getUsageCount($lead, $meetingType) + 1;
        $noteText = "{$meetingType->value}";
        
        if ($meetingType->hasUsageLimit()) {
            $noteText .= " #{$usageCount}";
        }
        
        $noteText .= " geboekt voor {$context['selectedDate']} om {$context['selectedTime']}";
        
        if ($context['coachNote'] ?? null) {
            $noteText .= "\n" . $context['coachNote'];
        }

        $this->addCoachNoteIfProvided(
            $leadId,
            $noteText,
            $context['fromCall'] ?? false ? 'Telefoon' : 'Afspraak',
            $context['eventDate'] ?? null
        );

        $lead->set('cStage', LeadStage::INTRO_SCHEDULED->value);
        $lead->set('introMeetingType', $meetingType->value);
        $this->entityManager->saveEntity($lead);

        $this->followUpService->clearFollowUpAction($leadId);

        return $result;
    }
}