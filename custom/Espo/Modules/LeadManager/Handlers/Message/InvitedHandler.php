<?php

namespace Espo\Modules\LeadManager\Handlers\Message;

use Espo\Custom\Enums\IntroMeetingType;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\IntroMeetingService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
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
        return [];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = new OutcomeResult();
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'Bericht',
            $context['eventDate'] ?? null
        );

        $this->followUpService->clearFollowUpAction($leadId);

        $GLOBALS['log']->info('InvitedHandler: ' . json_encode($context));

        if (!isset($context['calendarId'], $context['selectedDate'], $context['selectedTime'])) {
            return $result;
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

        if ($meetingType && !$this->introMeetingService->canBook($lead, $meetingType)) {
            throw new \RuntimeException("Lead cannot book {$meetingType->value}");
        }

        $this->meetingService->createInternalMeeting(
            $context['calendarId'],
            $lead,
            $context['selectedDate'],
            $context['selectedTime'],
            $context['coachNote'] ?? null
        );

        if ($calendarType === 'kickstart') {
            return $this->handleKickstartBooking($lead, $result, $context['eventDate'] ?? null);
        }

        if ($meetingType) {
            return $this->handleIntroBooking($lead, $result, $meetingType, $context['eventDate'] ?? null);
        }

        return $this->logBookingEvent($lead, $result, LeadEventType::BOOK_INTRO, $context['eventDate'] ?? null);
    }

    private function handleIntroBooking(
        Entity $lead,
        OutcomeResult $result,
        IntroMeetingType $meetingType,
        ?string $eventDate = null
    ): OutcomeResult {
        $result = $this->logBookingEvent($lead, $result, LeadEventType::BOOK_INTRO, $eventDate);

        $lead->set('cStage', LeadStage::INTRO_SCHEDULED->value);
        $lead->set('cMeetingType', $meetingType->value);
        $this->entityManager->saveEntity($lead);

        return $result;
    }

    private function handleKickstartBooking(
        Entity $lead,
        OutcomeResult $result,
        ?string $eventDate = null
    ): OutcomeResult {
        $result = $this->logBookingEvent($lead, $result, LeadEventType::KICKSTART_BOOKED, $eventDate);

        $lead->set('cStage', LeadStage::KS_PLANNED->value);
        $lead->set('cMeetingType', 'kickstart');
        $this->entityManager->saveEntity($lead);

        return $result;
    }

    private function logBookingEvent(
        Entity $lead,
        OutcomeResult $result,
        LeadEventType $eventType,
        ?string $eventDate = null
    ): OutcomeResult {
        $eventId = $this->eventLogService->logEvent(
            $lead->getId(),
            $eventType,
            $eventDate
        )['eventId'];

        return $result->addEventId($eventId);
    }
}
