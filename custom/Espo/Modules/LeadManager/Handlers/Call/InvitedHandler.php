<?php

namespace Espo\Modules\LeadManager\Handlers\Call;

use Espo\Custom\Enums\IntroMeetingType;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\IntroMeetingService;
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
        return [LeadEventType::CALLED];
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
        if ($calendarType === 'kickstart') {
            $this->meetingService->createInternalMeeting(
                $context['calendarId'],
                $lead,
                $context['selectedDate'],
                $context['selectedTime'],
                $context['coachNote'] ?? null
            );

            return $this->handleKickstartBooking($lead, $result, $context['eventDate'] ?? null);
        }

        if (IntroMeetingType::isIntroMeeting($calendarType)) {
            if (!$this->introMeetingService->canBook($lead, IntroMeetingType::fromCalendarType($calendarType))) {
                throw new \RuntimeException("Lead cannot book {$calendarType}");
            }

            $this->meetingService->createInternalMeeting(
                $context['calendarId'],
                $lead,
                $context['selectedDate'],
                $context['selectedTime'],
                $context['coachNote'] ?? null
            );

            return $this->handleIntroBooking($lead, $context, $result, $calendarType);
        }

        $this->meetingService->createInternalMeeting(
            $context['calendarId'],
            $lead,
            $context['selectedDate'],
            $context['selectedTime'],
            $context['coachNote'] ?? null
        );

        return $result;
    }

    private function handleIntroBooking(Entity $lead, array $context, OutcomeResult $result, string $calendarType): OutcomeResult
    {
        $meetingType = IntroMeetingType::fromCalendarType($calendarType);
        
        if (!$meetingType) {
            return $result;
        }

        if (!$this->introMeetingService->canBook($lead, $meetingType)) {
            throw new \RuntimeException("Lead cannot book {$meetingType->value}");
        }

        $eventId = $this->eventLogService->logEvent(
            $lead->getId(),
            LeadEventType::BOOK_INTRO,
            $context['eventDate'] ?? null
        )['eventId'];
        
        $result = $result->addEventId($eventId);

        // Update stage and type
        $lead->set('cStage', LeadStage::INTRO_SCHEDULED->value);
        $lead->set('cMeetingType', $meetingType->value);
        $this->entityManager->saveEntity($lead);

        return $result;
    }

    private function handleKickstartBooking(Entity $lead, OutcomeResult $result, ?string $eventDate = null): OutcomeResult
    {
        $eventId = $this->eventLogService->logEvent(
            $lead->getId(),
            LeadEventType::KICKSTART_BOOKED,
            $eventDate
        )['eventId'];

        $result = $result->addEventId($eventId);

        $lead->set('cStage', LeadStage::KS_PLANNED->value);
        $lead->set('cMeetingType', 'kickstart');
        $this->entityManager->saveEntity($lead);

        return $result;
    }
}
