<?php

namespace Espo\Modules\LeadManager\Handlers\Kickstart;

use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\EntityManager;

class BookedHandler extends AbstractOutcomeHandler
{
    public function __construct(
        LeadEventLogService $eventLogService,
        LeadNotesService $notesService,
        LeadFollowUpService $followUpService,
        private readonly LeadMeetingService $meetingService,
        private readonly EntityManager $entityManager,
        private readonly SlugService $slugService,
    ) {
        parent::__construct($eventLogService, $notesService, $followUpService);
    }

    public function getEventTypes(): array
    {
        return [LeadEventType::KICKSTART_BOOKED];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        if (!isset($context['calendarId'], $context['selectedDate'], $context['selectedTime'])) {
            throw new \InvalidArgumentException('Calendar ID, date, and time are required for booking');
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

        if ($calendar->get('type') !== 'kickstart') {
            throw new \InvalidArgumentException("Calendar type '{$calendar->get('type')}' is not kickstart");
        }

        $this->meetingService->createInternalMeeting(
            $context['calendarId'],
            $lead,
            $context['selectedDate'],
            $context['selectedTime'],
            $context['coachNote'] ?? null
        );

        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);

        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            'Afspraak',
            $context['eventDate'] ?? null
        );

        $lead->set('cMeetingType', 'kickstart');
        $lead->set('cStage', LeadStage::KS_PLANNED->value);
        $this->entityManager->saveEntity($lead);
        $this->followUpService->clearFollowUpAction($leadId);

        return $result;
    }
}
