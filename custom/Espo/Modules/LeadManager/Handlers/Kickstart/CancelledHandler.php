<?php

namespace Espo\Modules\LeadManager\Handlers\Kickstart;

use Espo\Custom\Enums\KickstartCalendarType;
use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\AbstractOutcomeHandler;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CancelledHandler extends AbstractOutcomeHandler
{
    protected const NOTE_SOURCE = 'Kickstart';
    protected const FOLLOW_UP_NOTE = 'Kickstart geannuleerd - Opnieuw inplannen';
    protected const BOOKED_EVENT = LeadEventType::KICKSTART_BOOKED;

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
        return [LeadEventType::KICKSTART_CANCELLED];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);

        $this->addCoachNoteIfProvided(
            $leadId,
            $context['coachNote'] ?? null,
            static::NOTE_SOURCE,
            $context['eventDate'] ?? null,
        );

        $meeting = $this->meetingService->findPlannedMeeting($leadId);
        $this->meetingService->markAsCancelled($meeting);

        $cancellationAction = $context['cancellationAction'] ?? null;

        switch ($cancellationAction) {
            case 'reschedule_now':
                if (!empty($context['calendarId']) && !empty($context['selectedDate'])) {
                    $lead = $this->entityManager->getEntityById('Lead', $leadId);
                    if ($lead instanceof Entity) {
                        $this->meetingService->createInternalMeeting(
                            $context['calendarId'],
                            $lead,
                            $context['selectedDate'],
                            $context['selectedTime'] ?? '',
                            $context['coachNote'] ?? null,
                        );

                        $this->syncMeetingType($lead, $context['calendarId']);

                        $eventId = $this->eventLogService->logEvent(
                            $leadId,
                            static::BOOKED_EVENT,
                            $context['eventDate'] ?? null,
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
                    $context['eventDate'] ?? null,
                )['eventId'];
                $result = $result->addEventId($eventId);

                $this->handleFollowUp(
                    $leadId,
                    $context['callAgainDateTime'] ?? null,
                    static::FOLLOW_UP_NOTE,
                );
                break;

            case 'cancel_stop':
                $eventId = $this->eventLogService->logEvent(
                    $leadId,
                    LeadEventType::CANCELLED_STOP,
                    $context['eventDate'] ?? null,
                )['eventId'];
                $result = $result->addEventId($eventId);

                $this->followUpService->clearFollowUpAction($leadId);
                break;
        }

        return $result;
    }

    /**
     * Keep the lead's meeting type in line with the calendar it was rescheduled on,
     * e.g. when a kickstart is moved to an online kickstart (or vice versa).
     */
    protected function syncMeetingType(Entity $lead, string $calendarIdentifier): void
    {
        $calendarId = $this->slugService->resolve('CCalendar', $calendarIdentifier);
        $calendar = $calendarId ? $this->entityManager->getEntityById('CCalendar', $calendarId) : null;
        $calendarType = $calendar instanceof Entity ? (string) $calendar->get('type') : null;

        if (!KickstartCalendarType::isKickstart($calendarType) || $lead->get('cMeetingType') === $calendarType) {
            return;
        }

        $lead->set('cMeetingType', $calendarType);
        $this->entityManager->saveEntity($lead);
    }
}
