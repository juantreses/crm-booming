<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Modules\Booking\Services\BookingService;

readonly class LeadMeetingService
{
    public function __construct(
        private EntityManager $entityManager,
        private BookingService $bookingService,
    ) {}

    public function createInternalMeeting(
        string $calendarId,
        Entity $lead,
        string $selectedDate,
        string $selectedTime,
        ?string $coachNote = null
    ): void {
        $this->bookingService->createInternalMeeting(
            $calendarId,
            $lead,
            $selectedDate,
            $selectedTime,
            $coachNote
        );
    }

    public function findPlannedMeeting(string $leadId): ?Entity
    {
        return $this->entityManager->getRDBRepository('Meeting')
            ->where([
                'parentId' => $leadId,
                'parentType' => 'Lead',
                'status' => ['Planned', 'Tentative'],
            ])
            ->order('dateStart', 'ASC')
            ->findOne();
    }

    public function setMeetingStatus(Entity $meeting, string $status): void
    {
        $meeting->set('status', $status);
        $this->entityManager->saveEntity($meeting);
    }

    public function markAsHeld(?Entity $meeting): void
    {
        if ($meeting) {
            $this->setMeetingStatus($meeting, 'Held');
        }
    }

    public function markAsNotHeld(?Entity $meeting): void
    {
        if ($meeting) {
            $this->setMeetingStatus($meeting, 'Not Held');
        }
    }

    public function markAsCancelled(?Entity $meeting): void
    {
        if ($meeting) {
            $this->setMeetingStatus($meeting, 'Cancelled');
        }
    }
}