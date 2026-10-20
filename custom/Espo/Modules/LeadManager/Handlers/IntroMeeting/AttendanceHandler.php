<?php

namespace Espo\Modules\LeadManager\Handlers\IntroMeeting;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\HomRating;
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
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Intro Meeting Attendance Handler
 * 
 * Handles when lead attends an intro meeting
 * Determines next stage based on:
 * - Meeting type (SPARK, BWS, HOM)
 * - Usage count (for SPARK)
 * - HOM score (A/B -> SPARK/Kickstart, C/D -> Royalty)
 * - Immediate next booking (optional)
 */
class AttendanceHandler extends AbstractOutcomeHandler
{
    public function __construct(
        LeadEventLogService $eventLogService,
        LeadNotesService $notesService,
        LeadFollowUpService $followUpService,
        private readonly EntityManager $entityManager,
        private readonly IntroMeetingService $introMeetingService,
        private readonly LeadMeetingService $meetingService,
        private readonly SlugService $slugService,
    ) {
        parent::__construct($eventLogService, $notesService, $followUpService);
    }

    public function getEventTypes(): array
    {
        return [LeadEventType::ATTEND_INTRO];
    }

    public function handle(string $leadId, array $context): OutcomeResult
    {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            throw new \RuntimeException("Lead not found: {$leadId}");
        }

        // Get intro meeting type
        $meetingType = $this->introMeetingService->getIntroMeetingType($lead);
        
        if (!$meetingType) {
            throw new \RuntimeException("Lead does not have an intro meeting type set");
        }

        $homRating = $this->resolveHomRating($meetingType, $context['homRating'] ?? null);

        // Check if they're booking next meeting immediately
        $hasImmediateBooking = !empty($context['nextBooking']);

        if ($hasImmediateBooking) {
            $this->assertNextBookingAllowed(
                $homRating,
                $this->getNextBookingCalendar($context)->get('type')
            );
        }

        // Record attendance and get suggested next stage
        $nextStage = $this->introMeetingService->recordAttendance($lead, $meetingType, $homRating);
        
        if ($hasImmediateBooking) {
            return $this->handleImmediateBooking($lead, $context, $meetingType, $homRating);
        } else {
            return $this->handleStandardAttendance($lead, $context, $meetingType, $nextStage, $homRating);
        }
    }

    private function resolveHomRating(IntroMeetingType $meetingType, ?HomRating $homRating): ?HomRating
    {
        if ($meetingType !== IntroMeetingType::HOM) {
            if ($homRating) {
                throw new BadRequest('HOM-score is enkel mogelijk bij een HOM.');
            }

            return null;
        }

        if (!$homRating) {
            throw new BadRequest('HOM-score (A/B/C/D) is verplicht.');
        }

        return $homRating;
    }

    private function buildAttendanceNote(?HomRating $homRating, string $noteText): string
    {
        if ($homRating) {
            $noteText .= "\nHOM-score: {$homRating->value}";
        }

        return $noteText;
    }

    /**
     * Calendar types a lead may book right after attending this intro
     */
    private function assertNextBookingAllowed(?HomRating $homRating, string $nextCalendarType): void
    {
        if (!$homRating) {
            if ($nextCalendarType === 'royalty') {
                throw new BadRequest('Een RI kan enkel geboekt worden na een HOM met score C of D.');
            }

            return;
        }

        $allowed = $homRating->leadsToRoyalty()
            ? ['royalty' => 'RI']
            : ['spark' => 'SPARK', 'kickstart' => 'Kickstart'];

        if (!array_key_exists($nextCalendarType, $allowed)) {
            throw new BadRequest("Na een HOM met score {$homRating->value} kan je enkel " . implode(' of ', $allowed) . ' boeken.');
        }
    }

    private function getNextBookingCalendar(array $context): Entity
    {
        $nextCalendarId = $this->slugService->resolve('CCalendar', $context['nextBooking']['calendarId']);
        $nextCalendar = $this->entityManager->getEntityById('CCalendar', $nextCalendarId);

        if (!$nextCalendar) {
            throw new \RuntimeException("Next booking calendar not found");
        }

        return $nextCalendar;
    }

    /**
     * Handle when lead books next meeting immediately after attendance
     */
    private function handleImmediateBooking(Entity $lead, array $context, IntroMeetingType $attendedType, ?HomRating $homRating): OutcomeResult
    {
        $leadId = $lead->getId();
        
        $nextCalendarType = $this->getNextBookingCalendar($context)->get('type');
        
        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);

        $usageCount = $this->introMeetingService->getUsageCount($lead, $attendedType);
        $remaining = $this->introMeetingService->getRemainingUsage($lead, $attendedType);
        
        $noteText = "{$attendedType->value}";
        if ($attendedType->hasUsageLimit()) {
            $noteText .= " #{$usageCount}";
        }
        $noteText .= " aanwezig";
        
        if ($remaining > 0 && $attendedType->hasUsageLimit()) {
            $noteText .= ". Nog {$remaining} sessie(s) beschikbaar";
        }

        $noteText = $this->buildAttendanceNote($homRating, $noteText);
        
        if ($context['coachNote'] ?? null) {
            $noteText .= "\n" . $context['coachNote'];
        }
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $noteText,
            'Afspraak',
            $context['eventDate'] ?? null
        );

        $this->meetingService->createInternalMeeting(
            $context['nextBooking']['calendarId'],
            $lead,
            $context['nextBooking']['selectedDate'],
            $context['nextBooking']['selectedTime'],
            null
        );

        if ($nextCalendarType === 'kickstart') {
            $lead->set('cStage', LeadStage::KS_PLANNED->value);
            $lead->set('cMeetingType', 'kickstart');
            $bookingNote = "Direct Kickstart geboekt na {$attendedType->value}";
        } else if ($nextCalendarType === 'royalty') {
            $lead->set('cStage', LeadStage::RI_PLANNED->value);
            $lead->set('cMeetingType', 'royalty');
            $bookingNote = "Direct RI geboekt na {$attendedType->value}";
        } else {
            $nextMeetingType = IntroMeetingType::fromCalendarType($nextCalendarType);
            if ($nextMeetingType) {
                if (!$this->introMeetingService->canBook($lead, $nextMeetingType)) {
                    throw new \RuntimeException("Lead cannot book {$nextMeetingType->value}");
                }

                $lead->set('cStage', LeadStage::INTRO_SCHEDULED->value);
                $lead->set('cMeetingType', $nextMeetingType->value);
                $bookingNote = "Direct {$nextMeetingType->value} geboekt";
            } else {
                throw new \InvalidArgumentException("Invalid next booking calendar type: {$nextCalendarType}");
            }
        }

        $this->addCoachNoteIfProvided(
            $leadId,
            $bookingNote,
            'Afspraak',
            $context['eventDate'] ?? null
        );

        $bookingEventType = match ($nextCalendarType) {
            'kickstart' => LeadEventType::KICKSTART_BOOKED,
            'royalty' => LeadEventType::ROYALTY_BOOKED,
            default => LeadEventType::BOOK_INTRO,
        };

        $eventId = $this->eventLogService->logEvent(
            $leadId,
            $bookingEventType,
            $context['eventDate'] ?? null
        )['eventId'];

        $result = $result->addEventId($eventId);

        $this->entityManager->saveEntity($lead);
        $this->followUpService->clearFollowUpAction($leadId);

        $meeting = $this->meetingService->findPlannedMeeting($leadId);
        $this->meetingService->markAsHeld($meeting);

        return $result;
    }

    private function handleStandardAttendance(Entity $lead, array $context, IntroMeetingType $meetingType, LeadStage $nextStage, ?HomRating $homRating): OutcomeResult
    {
        $leadId = $lead->getId();

        $result = $this->logEvents($leadId, $context['eventDate'] ?? null);

        $usageCount = $this->introMeetingService->getUsageCount($lead, $meetingType);
        $remaining = $this->introMeetingService->getRemainingUsage($lead, $meetingType);
        
        $noteText = "{$meetingType->value}";
        if ($meetingType->hasUsageLimit()) {
            $noteText .= " #{$usageCount}";
        }
        $noteText .= " aanwezig";
        
        if ($remaining > 0 && $meetingType->hasUsageLimit()) {
            $noteText .= ". Nog {$remaining} sessie(s) beschikbaar.";
        } else if ($remaining === 0 && $meetingType->hasUsageLimit()) {
            $noteText .= ". Alle sessies gebruikt. Klaar voor Kickstart!";
        }

        $noteText = $this->buildAttendanceNote($homRating, $noteText);
        
        if ($context['coachNote'] ?? null) {
            $noteText .= "\n" . $context['coachNote'];
        }
        
        $this->addCoachNoteIfProvided(
            $leadId,
            $noteText,
            'Afspraak',
            $context['eventDate'] ?? null
        );

        // Update stage
        $lead->set('cStage', $nextStage->value);
        $this->entityManager->saveEntity($lead);

        if ($nextStage === LeadStage::INTRO_ATTENDED && $homRating) {
            $this->followUpService->setFollowUpActionText($leadId, "SPARK of Kickstart boeken");
        } else if ($nextStage === LeadStage::INTRO_ATTENDED) {
            $this->followUpService->setFollowUpActionText(
                $leadId,
                "Opnieuw {$meetingType->value} boeken of Kickstart"
            );
        } else if ($nextStage === LeadStage::BOOK_KS) {
            $this->followUpService->setFollowUpActionText($leadId, "Kickstart boeken");
        } else if ($nextStage === LeadStage::BOOK_RI) {
            $this->followUpService->setFollowUpActionText($leadId, "RI boeken");
        }

        $meeting = $this->meetingService->findPlannedMeeting($leadId);
        $this->meetingService->markAsHeld($meeting);

        return $result;
    }
}
