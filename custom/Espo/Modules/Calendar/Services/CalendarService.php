<?php

namespace Espo\Modules\Calendar\Services;

use DateTime;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\SthCollection;
use Exception;

readonly class CalendarService
{
    public function __construct(
        private EntityManager $entityManager,
        private SlugService $slugService,
    ) {}

    /**
     * Get available slots for a calendar on a specific date
     * 
     * @param string $identifier Calendar slug or ID
     * @param string $dateString Date in Y-m-d format
     * @param string|null $locationIdentifier Location slug or ID (optional)
     * @param string|null $coachIdentifier Coach slug or ID (optional, filters by team)
     * @throws Exception
     */
    public function getAvailableSlots(
        string $identifier, 
        string $dateString, 
        ?string $locationIdentifier = null,
        ?string $coachIdentifier = null
    ): array {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        $locationId = $this->slugService->resolve('CLocation', $locationIdentifier);
        $teamId = $this->resolveTeamFromCoach($coachIdentifier);

        $GLOBALS['log']->debug("CalendarDebug: Coach Identifier '{$coachIdentifier}' resolved to Team ID: " . ($teamId ?? 'NULL'));
        
        $calendar = $this->validateCalendar($calendarId);
        $availabilities = $this->getAvailabilityForDate($calendar, $dateString, $locationId, $teamId);
        
        if (!$availabilities || (is_countable($availabilities) && count($availabilities) === 0)) {
            return [];
        }

        $availabilities = $this->normalizeAvailabilities($availabilities);

        $locationIds = [];
        foreach($availabilities as $availability) {
            if ($lid = $availability->get('locationId')) {
                $locationIds[] = $lid;
            }
        }

        $locationMap = $this->buildLocationMap($locationIds);
        $calendarConfig = $this->getCalendarConfig($calendar, $dateString);
        
        $allSlots = [];
        foreach ($availabilities as $availability) {
            $locId = $availability->get('locationId');
            $locationData = $locId ? ($locationMap[$locId] ?? null) : null;

            $slots = $this->generateSlotsForAvailability($availability, $dateString, $calendarConfig, $locationData);
            $allSlots = array_merge($allSlots, $slots);
        }

        return $this->deduplicateAndSortSlots($allSlots);
    }

    /**
     * Resolve team ID from coach identifier
     * 
     * @param string|null $coachIdentifier Coach slug or ID
     * @return string|null Team ID if found
     */
    private function resolveTeamFromCoach(?string $coachIdentifier): ?string
    {
        if (!$coachIdentifier) {
            return null;
        }

        $userId = $this->slugService->resolve('User', $coachIdentifier);
        $GLOBALS['log']->debug("CalendarDebug userid: $userId");

        if (!$userId) {
            return null;
        }

        $team = $this->entityManager
            ->getRDBRepository('CTeam')
            ->where(['assignedUserId' => $userId])
            ->findOne();

            $GLOBALS['log']->debug("CalendarDebug:" . ($team ? $team->getId() : 'NULL'));
    

        return $team?->getId();
    }

    /**
     * Build location data map
     */
    private function buildLocationMap(array $locationIds): array
    {
        if (empty($locationIds)) {
            return [];
        }

        $locations = $this->entityManager->getRDBRepository('CLocation')
            ->where(['id' => array_unique($locationIds)])
            ->find();
        
        $locationMap = [];
        foreach($locations as $loc) {
            $locationMap[$loc->getId()] = [
                'id' => $loc->getId(),
                'name' => $loc->get('name'),
                'addressStreet' => $loc->get('addressStreet'),
                'addressCity' => $loc->get('addressCity'),
                'addressState' => $loc->get('addressState'),
                'addressCountry' => $loc->get('addressCountry'),
                'addressPostalCode' => $loc->get('addressPostalCode'),
            ];
        }

        return $locationMap;
    }

    public function getBookableCalendars(): array
    {
        $calendars = $this->entityManager->getRDBRepository('CCalendar')
            ->where([
                'isActive' => true,
                'isDirectBookable' => true,
                'deleted' => false,
            ])
            ->find();

        $result = [];
        foreach ($calendars as $calendar) {
            $result[] = [
                'id' => $calendar->get('id'),
                'name' => $calendar->get('name'),
                'type' => $calendar->get('type'),
                'identifier' => $calendar->get('slug')
            ];
        }
        
        return $result;
    }

    /**
     * Get upcoming slots for a calendar
     * 
     * @param string $identifier Calendar slug or ID
     * @param string|null $coachIdentifier Coach slug or ID (filters by team)
     */
    public function getUpcomingSlots(string $identifier, ?string $coachIdentifier = null): array
    {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        if (!$calendarId) {
            throw new BadRequest("Kalender ID onbekend: $identifier");
        }
        
        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
        if (!$calendar) {
            throw new NotFound("Kalender niet gevonden");
        }

        $daysRange = $calendar->get('maxBookingRange') ?? 14; 
        
        if ($daysRange > 60) {
            $daysRange = 60; 
        }

        $slots = [];
        $currentDate = new \DateTime();

        for ($i = 0; $i < $daysRange; $i++) {
            $dateString = $currentDate->format('Y-m-d');
            
            try {
                $daySlots = $this->getAvailableSlots($identifier, $dateString, null, $coachIdentifier);
                
                if (!empty($daySlots)) {
                    $slots[$dateString] = $daySlots;
                }
            } catch (\Exception $e) {
                $GLOBALS['log']->error('CALENDAR: ' . $e->getMessage());
            }
            
            $currentDate->modify('+1 day');
        }

        return $slots;
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

    private function getCalendarConfig(Entity $calendar, string $dateString): array
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
            'bookings' => $this->getExistingBookings($calendar, $dateString),
            'firstBookableMoment' => (clone $now)->modify("+$minLeadTime hours"),
            'tzLocal' => $tzLocal,
            'tzUTC' => $tzUTC,
        ];
    }

    private function generateSlotsForAvailability(Entity $availability, string $dateString, array $config, ?array $locationData = null): array
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
            $slot = $this->createSlot($currentPointer, $dateString, $config, $tzLocal, $locationData);
            
            if (!$slot['isBlocked']) {
                $slots[] = $slot;
            }

            $step = $duration + $buffer;
            $currentPointer->modify("+$step minutes");
        }

        return $slots;
    }

    private function createSlot(DateTime $currentPointer, string $dateString, array $config, DateTimeZone $tzLocal, ?array $locationData = null): array
    {
        $displayPointer = clone $currentPointer;
        $displayPointer->setTimezone($tzLocal);

        $slotStart = $currentPointer->format('H:i');
        $slotEnd = (clone $currentPointer)->modify("+{$config['duration']} minutes")->format('H:i');

        $isBlocked = $this->isSlotBlocked($slotStart, $slotEnd, $config['blockingPeriods']);
        $isTooSoon = $this->isSlotTooSoon($displayPointer, $dateString, $config['firstBookableMoment']);
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
            'locationId' => $locationData['id'] ?? null,
            'locationName' => $locationData['name'] ?? null,
            'locationAddressStreet' => $locationData['addressStreet'] ?? null,
            'locationAddressCity' => $locationData['addressCity'] ?? null,
            'locationAddressState' => $locationData['addressState'] ?? null,
            'locationAddressCountry' => $locationData['addressCountry'] ?? null,
            'locationAddressPostalCode' => $locationData['addressPostalCode'] ?? null,
        ];
    }

    private function isSlotBlocked(string $slotStart, string $slotEnd, $blockingPeriods): bool
    {
        foreach ($blockingPeriods as $block) {
            $blockStart = $block['startTime'];
            $blockEnd = $block['endTime'];

            $blockBuffer = $block['_bufferTime'] ?? 0;
            if ($blockBuffer > 0) {
                $blockEndObj = new DateTime($blockEnd);
                $blockEndObj->modify("+$blockBuffer minutes");
                $blockEnd = $blockEndObj->format('H:i');
            }

            if ($slotStart <= $blockEnd && $slotEnd >= $blockStart) {
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

    private function getBlockingAvailability($currentCalendar, $dateString): array
    {
        $collection = $this->entityManager->getRDBRepository('CAvailability')
            ->join('calendar') 
            ->where([
                'calendarId!=' => $currentCalendar->get('id'),
                'type' => 'specific',
                'date' => $dateString,
                'calendar.priority>' => $currentCalendar->get('priority'),
                'calendar.isActive' => true,
            ])
            ->find();

        $result = [];
        
        foreach ($collection as $entity) {
            $data = (array) $entity->getValueMap();
            
            $blockingCalId = $entity->get('calendarId');
            
            if ($blockingCalId) {
                $blockingCal = $this->entityManager->getEntityById('CCalendar', $blockingCalId);
                $data['_bufferTime'] = $blockingCal ? $blockingCal->get('bufferTime') : 0;
            } else {
                $data['_bufferTime'] = 0;
            }
            
            $result[] = $data;
        }
        
        return $result;
    }

    /**
     * Get availabilities for a specific date, filtered by team if provided
     * 
     * @param Entity $calendar
     * @param string $dateString
     * @param string|null $locationId
     * @param string|null $teamId Filter by team (null = no filter)
     * @return SthCollection|EntityCollection
     */
    private function getAvailabilityForDate(
        Entity $calendar, 
        string $dateString, 
        ?string $locationId = null,
        ?string $teamId = null
    ): SthCollection|EntityCollection {
        $dayNum = (string) date('w', strtotime($dateString));

        $criteria = [
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
        ];

        if ($locationId) {
            $criteria['locationId'] = $locationId;
        }

        if ($teamId) {
            $repo = $this->entityManager->getRDBRepository('CAvailability');
            
            return $repo
                ->leftJoin('cTeams', 'teamJoin')
                ->where($criteria)
                ->where([
                    'OR' => [
                        ['teamJoin.id' => $teamId],
                        ['teamJoin.id' => null]
                    ]
                ])
                ->distinct()
                ->find();
        }

        return $this->entityManager->getRDBRepository('CAvailability')
            ->select()
            ->where($criteria)
            ->find();
    }

    private function getExistingBookings($calendar, $dateString): array
    {
        $bookingList = $this->entityManager->getRDBRepository('Meeting')
            ->where([
                'cCalendarId' => $calendar->get('id'),
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
     * Get month availability
     * 
     * @param string $identifier Calendar slug or ID
     * @param int $year
     * @param int $month
     * @param string|null $locationIdentifier Location slug or ID
     * @param string|null $coachIdentifier Coach slug or ID (filters by team)
     * @throws Exception
     */
    public function getMonthAvailability(
        string $identifier, 
        int $year, 
        int $month, 
        ?string $locationIdentifier = null,
        ?string $coachIdentifier = null
    ): array {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        $locationId = $this->slugService->resolve('CLocation', $locationIdentifier);

        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
        if (!$calendar) {
            return [];
        }

        $availableDates = [];
        $daysInMonth = (int) (new \DateTime("$year-$month-01"))->format('t');

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);

            if (!$this->isDateWithinRange($dateString, $calendar)) {
                continue;
            }

            $slots = $this->getAvailableSlots($calendarId, $dateString, $locationId, $coachIdentifier);

            foreach ($slots as $slot) {
                if ($slot['isBookable'] ?? false) {
                    $GLOBALS['log']->debug("CalendarDebug: $dateString: " . json_encode($slot));
                    $availableDates[] = $dateString;
                    break;
                }
            }
        }
        return $availableDates;
    }

    private function isDateWithinRange(string $dateString, Entity $calendar): bool
    {
        $maxDays = $calendar->get('maxBookingRange') ?? 30;

        $today = new \DateTime('today');
        $requestedDate = new \DateTime($dateString);
        $maxDate = (clone $today)->modify("+$maxDays days");

        return $requestedDate >= $today && $requestedDate <= $maxDate;
    }

    /**
     * @throws Exception
     */
    public function getSettings(string $identifier): array
    {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        if (!$calendarId) {
            throw new Exception("Kalender niet gevonden voor identifier: {$identifier}");
        }

        $calendar = $this->validateCalendar($calendarId);

        return [
            'name' => $calendar->get('name'),
            'description' => $calendar->get('description'),
            'duration' => $calendar->get('duration'),
            'maxBookingRange' => $calendar->get('maxBookingRange') ?? 30,
        ];
    }
}