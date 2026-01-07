<?php

namespace Espo\Modules\Calendar\Services;

use DateTime;
use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\SthCollection;
use Exception;
use function cal_days_in_month;

class CalendarService
{
    public function __construct(
        private readonly EntityManager $entityManager
    ) {}

    /**
     * @throws Exception
     */
    public function getAvailableSlots($calendarId, $dateString): array
    {
        $calendar = $this->entityManager
            ->getRDBRepository('CCalendar')
            ->getById($calendarId);

        if (!$calendar) {
            throw new Exception("Kalender niet gevonden.");
        }

        if (!$calendar->get('isActive')) {
            throw new Exception("Kalender niet actief.");
        }

        $blockingPeriods = $this->getBlockingAvailability($calendar, $dateString);
        $availability = $this->getAvailabilityForDate($calendar, $dateString);
        if (!$availability) {
            return ["no availability", date('w', strtotime($dateString))];
        }

        $duration = $calendar->get('duration');
        $buffer = $calendar->get('bufferTime');
        $maxSeats = $calendar->get('seatsPerMeeting');

        $startTime = new DateTime($dateString . ' ' . $availability->get('startTime'));
        $endTime = new DateTime($dateString . ' ' . $availability->get('endTime'));

        $bookings = $this->getExistingBookings($calendarId, $dateString);

        $slots = [];
        $currentPointer = clone $startTime;

        $maxTime = (clone $endTime)->modify("-$duration minutes");

        $now = new DateTime();
        $minLeadTime = $calendar->get('minLeadTime') ?? 0;

        $firstBookableMoment = (clone $now)->modify("+$minLeadTime hours");

        while ($currentPointer <= $maxTime) {
            $slotStart = $currentPointer->format('H:i');
            $slotEnd = (clone $currentPointer)->modify("+{$duration} minutes")->format('H:i');

            $isBlocked = false;
            foreach ($blockingPeriods as $block) {
                $blockStart = $block->get('startTime');
                $blockEnd = $block->get('endTime');

                $calendar = $this->entityManager
                    ->getRelation($block, 'calendar')
                    ->findOne();

                $blockBuffer = $calendar?->get('bufferTime');
                if ($blockBuffer > 0) {
                    $blockEndObj = new DateTime($blockEnd);
                    $blockEndObj->modify("+$blockBuffer minutes");
                    $blockEnd = $blockEndObj->format('H:i');
                }

                if ($slotStart < $blockEnd && $slotEnd > $blockStart) {
                    $isBlocked = true;
                    break;
                }
            }

            $slotStartDateTime = new DateTime($dateString . ' ' . $currentPointer->format('H:i'));
            $isTooSoon = ($slotStartDateTime < $firstBookableMoment);

            $occupiedSeats = $bookings[$slotStart] ?? 0;
            $availableSeats = $maxSeats - $occupiedSeats;
            $hasSeats = $availableSeats > 0;
            $isBookable = !$isBlocked && !$isTooSoon && $hasSeats;

            if (!$isBlocked) {
                $slots[] = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'availableSeats' => $availableSeats,
                    'isBookable' => $isBookable,
                    'reason' => $isTooSoon ? 'te kort dag' : ($hasSeats ? '' : 'volzet'),
                ];
            }

            $step = $duration + $buffer;
            $currentPointer->modify("+$step minutes");
        }

        return $slots;
    }

    private function getBlockingAvailability($currentCalendar, $dateString): SthCollection|EntityCollection
    {
        return $this->entityManager->getRDBRepository('CAvailability')
            ->join('CCalendar', 'calendar')
            ->where([
                'calendarId!=' => $currentCalendar->get('id'),
                'type' => 'specific',
                'date' => $dateString,
                'calendar.priority >' => $currentCalendar->get('priority'),
                'calendar.isActive' => true,
            ])
            ->find();
    }

    private function getAvailabilityForDate($calendar, $dateString): ?Entity
    {
        $dayNum = (string) date('w', strtotime($dateString));

        return $this->entityManager->getRDBRepository('CAvailability')
            ->select()
            ->where([
                'calendarId' => $calendar->get('id'),
                [
                    'OR' => [
                        [
                            'type' => 'specific',
                            'date' => $dateString
                        ],
                        [
                            'type' => 'recurrent',
                            // Gebruik de exacte veldnaam uit je entityDef met een asterisk voor LIKE
                            'daysOfWeek*' => '%' . $dayNum . '%'
                        ]
                    ]
                ]
            ])
            ->findOne();
    }

    private function getExistingBookings($calendarId, $dateString): array
    {
        $bookingList = $this->entityManager->getRDBRepository('Meeting')
            ->where([
                'cCalendarId' => $calendarId,
                'dateStart>=' => $dateString . ' 00:00:00',
                'dateStart<=' => $dateString . ' 23:59:59',
                'status!=' => ['Cancelled', 'Tentative']
            ])
            ->find();

        $counts = [];

        foreach ($bookingList as $booking) {
            $startTime = new \DateTime($booking->get('dateStart'));
            $timeKey = $startTime->format('H:i');

            if (!isset($counts[$timeKey])) {
                $counts[$timeKey] = 0;
            }

            $counts[$timeKey]++;
        }

        return $counts;
    }

    /**
     * @throws Exception
     */
    public function getMonthAvailability(string $calendarId, int $year, int $month): array
    {
        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
        $availableDates = [];
        $daysInMonth = (int) (new \DateTime("$year-$month-01"))->format('t');

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);

            // Gebruik de nieuwe range check
            if (!$this->isDateWithinRange($dateString, $calendar)) {
                continue;
            }

            $slots = $this->getAvailableSlots($calendarId, $dateString);

            foreach ($slots as $slot) {
                if ($slot['isBookable'] ?? false) {
                    $availableDates[] = $dateString;
                    break;
                }
            }
        }
        return $availableDates;
    }

    private function isDateWithinRange(string $dateString, Entity $calendar): bool
    {
        $maxDays = $calendar->get('maxBookingRange') ?? 30; // Gebruik de waarde uit CRM of default naar 30

        $today = new \DateTime('today');
        $requestedDate = new \DateTime($dateString);
        $maxDate = (clone $today)->modify("+$maxDays days");

        // De datum mag niet in het verleden liggen én niet verder dan de max range
        return $requestedDate >= $today && $requestedDate <= $maxDate;
    }
}