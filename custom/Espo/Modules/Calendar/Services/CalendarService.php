<?php

namespace Espo\Modules\Calendar\Services;

use DateTime;
use DateTimeZone;
use Espo\Core\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\SthCollection;
use Exception;

readonly class CalendarService
{
    public function __construct(
        private EntityManager   $entityManager,
    ) {}

    /**
     * @throws Exception
     */
    public function getAvailableSlots($calendarId, $dateString): array
    {
        $calendar = $this->validateCalendar($calendarId);
        $availabilities = $this->getAvailabilityForDate($calendar, $dateString);
        
        if (!$availabilities || (is_countable($availabilities) && count($availabilities) === 0)) {
            return [];
        }

        $availabilities = $this->normalizeAvailabilities($availabilities);
        $calendarConfig = $this->getCalendarConfig($calendar, $calendarId, $dateString);
        
        $allSlots = [];
        foreach ($availabilities as $availability) {
            $slots = $this->generateSlotsForAvailability($availability, $dateString, $calendarConfig);
            $allSlots = array_merge($allSlots, $slots);
        }

        return $this->deduplicateAndSortSlots($allSlots);
    }

    /**
     * @throws Exception
     */
    private function validateCalendar(string $calendarId): Entity
    {
        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
        
        if (!$calendar) {
            throw new Exception("Kalender niet gevonden.");
        }

        if (!$calendar->get('isActive')) {
            throw new Exception("Kalender niet actief.");
        }

        return $calendar;
    }

    private function normalizeAvailabilities($availabilities): array
    {
        if ($availabilities instanceof Entity) {
            return [$availabilities];
        }
        
        return is_array($availabilities) ? $availabilities : iterator_to_array($availabilities);
    }

    private function getCalendarConfig(Entity $calendar, string $calendarId, string $dateString): array
    {
        $tzLocal = new DateTimeZone('Europe/Brussels');
        $tzUTC = new DateTimeZone('UTC');
        $now = new DateTime();
        $minLeadTime = $calendar->get('minLeadTime') ?? 0;

        return [
            'calendar' => $calendar,
            'duration' => $calendar->get('duration'),
            'buffer' => $calendar->get('bufferTime'),
            'maxSeats' => $calendar->get('seatsPerMeeting'),
            'blockingPeriods' => $this->getBlockingAvailability($calendar, $dateString),
            'bookings' => $this->getExistingBookings($calendarId, $dateString),
            'firstBookableMoment' => (clone $now)->modify("+$minLeadTime hours"),
            'tzLocal' => $tzLocal,
            'tzUTC' => $tzUTC,
        ];
    }

    private function generateSlotsForAvailability(Entity $availability, string $dateString, array $config): array
    {
        $slots = [];
        $tzLocal = $config['tzLocal'];
        $tzUTC = $config['tzUTC'];
        $duration = $config['duration'];
        $buffer = $config['buffer'];

        $startTime = new DateTime($dateString . ' ' . $availability->get('startTime'), $tzLocal);
        $endTime = new DateTime($dateString . ' ' . $availability->get('endTime'), $tzLocal);
        $startTimeUTC = (clone $startTime)->setTimezone($tzUTC);
        $endTimeUTC = (clone $endTime)->setTimezone($tzUTC);

        $currentPointer = clone $startTimeUTC;
        $maxTime = (clone $endTimeUTC)->modify("-$duration minutes");

        while ($currentPointer <= $maxTime) {
            $slot = $this->createSlot($currentPointer, $dateString, $config, $tzLocal);
            
            if (!$slot['isBlocked']) {
                $slots[] = $slot;
            }

            $step = $duration + $buffer;
            $currentPointer->modify("+$step minutes");
        }

        return $slots;
    }

    private function createSlot(DateTime $currentPointer, string $dateString, array $config, DateTimeZone $tzLocal): array
    {
        $displayPointer = clone $currentPointer;
        $displayPointer->setTimezone($tzLocal);

        $slotStart = $currentPointer->format('H:i');
        $slotEnd = (clone $currentPointer)->modify("+{$config['duration']} minutes")->format('H:i');

        $isBlocked = $this->isSlotBlocked($slotStart, $slotEnd, $config['blockingPeriods']);
        $isTooSoon = $this->isSlotTooSoon($currentPointer, $dateString, $config['firstBookableMoment']);
        $availableSeats = $this->getAvailableSeats($slotStart, $config['maxSeats'], $config['bookings']);
        $hasSeats = $availableSeats > 0;
        $isBookable = !$isBlocked && !$isTooSoon && $hasSeats;

        return [
            'start' => $displayPointer->format('H:i'),
            'end' => (clone $displayPointer)->modify("+{$config['duration']} minutes")->format('H:i'),
            'availableSeats' => $availableSeats,
            'isBookable' => $isBookable,
            'isBlocked' => $isBlocked,
            'reason' => $isTooSoon ? 'te kort dag' : ($hasSeats ? '' : 'volzet'),
        ];
    }

    private function isSlotBlocked(string $slotStart, string $slotEnd, $blockingPeriods): bool
    {
        foreach ($blockingPeriods as $block) {
            $blockStart = $block->get('startTime');
            $blockEnd = $block->get('endTime');

            $blockCalendar = $this->entityManager
                ->getRelation($block, 'calendar')
                ->findOne();

            $blockBuffer = $blockCalendar?->get('bufferTime');
            if ($blockBuffer > 0) {
                $blockEndObj = new DateTime($blockEnd);
                $blockEndObj->modify("+$blockBuffer minutes");
                $blockEnd = $blockEndObj->format('H:i');
            }

            if ($slotStart < $blockEnd && $slotEnd > $blockStart) {
                return true;
            }
        }

        return false;
    }

    private function isSlotTooSoon(DateTime $slotTime, string $dateString, DateTime $firstBookableMoment): bool
    {
        $slotStartDateTime = new DateTime($dateString . ' ' . $slotTime->format('H:i'));
        return $slotStartDateTime < $firstBookableMoment;
    }

    private function getAvailableSeats(string $slotStart, int $maxSeats, array $bookings): int
    {
        $occupiedSeats = $bookings[$slotStart] ?? 0;
        return max(0, $maxSeats - $occupiedSeats);
    }

    private function deduplicateAndSortSlots(array $slots): array
    {
        usort($slots, function($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        $uniqueSlots = [];
        $seenStarts = [];
        foreach ($slots as $slot) {
            if (!in_array($slot['start'], $seenStarts)) {
                $uniqueSlots[] = $slot;
                $seenStarts[] = $slot['start'];
            }
        }

        return $uniqueSlots;
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

    private function getAvailabilityForDate($calendar, $dateString): SthCollection|EntityCollection
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
                            'daysOfWeek*' => '%' . $dayNum . '%'
                        ]
                    ]
                ]
            ])
            ->find();
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