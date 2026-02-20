<?php

namespace Espo\Modules\Calendar\Services;

use DateTime;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Exception;

readonly class CalendarService
{
    public function __construct(
        private EntityManager $entityManager,
        private CalendarRepository $calendarRepository,
        private SlugService $slugService,
    ) {}

    /**
     * Get available slots for a calendar on a specific date
     * 
     * @throws BadRequest
     * @throws NotFound
     */
    public function getAvailableSlots(
        string $identifier, 
        string $dateString, 
        ?string $locationIdentifier = null,
        ?string $coachIdentifier = null
    ): array {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        if (!$calendarId) {
            throw new BadRequest("Calendar identifier not found: $identifier");
        }

        $locationId = $this->slugService->resolve('CLocation', $locationIdentifier);
        $teamId = $this->resolveTeamFromCoach($coachIdentifier);

        $calendar = $this->getValidatedCalendar($calendarId);
        $availabilities = $this->calendarRepository->getAvailabilitiesForDate(
            $calendar->getId(),
            $dateString,
            $locationId,
            $teamId
        );
        
        if (!$availabilities || (is_countable($availabilities) && count($availabilities) === 0)) {
            return [];
        }

        $availabilities = $this->normalizeAvailabilities($availabilities);

        $locationIds = $this->extractLocationIds($availabilities);
        $locationMap = $this->calendarRepository->getLocationsByIds($locationIds);
        $calendarConfig = $this->buildCalendarConfig($calendar, $dateString);
        
        $allSlots = [];
        foreach ($availabilities as $availability) {
            $locId = $availability->get('locationId');
            $locationData = $locId ? ($locationMap[$locId] ?? null) : null;

            $slots = $this->generateSlotsForAvailability(
                $availability,
                $dateString,
                $calendarConfig,
                $locationData
            );
            $allSlots = array_merge($allSlots, $slots);
        }

        return $this->deduplicateAndSortSlots($allSlots);
    }

    /**
     * Get bookable calendars
     */
    public function getBookableCalendars(): array
    {
        $calendars = $this->calendarRepository->getBookableCalendars();

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
     * @throws BadRequest
     * @throws NotFound
     */
    public function getUpcomingSlots(string $identifier, ?string $coachIdentifier = null): array
    {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        if (!$calendarId) {
            throw new BadRequest("Calendar identifier not found: $identifier");
        }
        
        $calendar = $this->calendarRepository->findCalendarById($calendarId);
        if (!$calendar) {
            throw new NotFound("Calendar not found");
        }

        $daysRange = min($calendar->get('maxBookingRange') ?? 14, 60);
        
        $slots = [];
        $currentDate = new DateTime();

        for ($i = 0; $i < $daysRange; $i++) {
            $dateString = $currentDate->format('Y-m-d');
            
            try {
                $daySlots = $this->getAvailableSlots($identifier, $dateString, null, $coachIdentifier);
                
                if (!empty($daySlots)) {
                    $slots[$dateString] = $daySlots;
                }
            } catch (Exception $e) {
                // Log but continue processing other dates
                error_log('Calendar slots error for ' . $dateString . ': ' . $e->getMessage());
            }
            
            $currentDate->modify('+1 day');
        }

        return $slots;
    }

    /**
     * Get month availability
     * 
     * @throws BadRequest
     * @throws NotFound
     */
    public function getMonthAvailability(
        string $identifier, 
        int $year, 
        int $month, 
        ?string $locationIdentifier = null,
        ?string $coachIdentifier = null
    ): array {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        if (!$calendarId) {
            throw new BadRequest("Calendar identifier not found: $identifier");
        }

        $calendar = $this->calendarRepository->findCalendarById($calendarId);
        if (!$calendar) {
            return [];
        }

        $availableDates = [];
        $daysInMonth = (int) (new DateTime("$year-$month-01"))->format('t');

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);

            if (!$this->isDateWithinRange($dateString, $calendar)) {
                continue;
            }

            $slots = $this->getAvailableSlots(
                $calendarId,
                $dateString,
                $this->slugService->resolve('CLocation', $locationIdentifier),
                $coachIdentifier
            );

            foreach ($slots as $slot) {
                if ($slot['isBookable'] ?? false) {
                    $availableDates[] = $dateString;
                    break;
                }
            }
        }

        return $availableDates;
    }

    /**
     * Get calendar settings
     * 
     * @throws BadRequest
     * @throws NotFound
     */
    public function getSettings(string $identifier): array
    {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);
        if (!$calendarId) {
            throw new BadRequest("Calendar identifier not found: $identifier");
        }

        $calendar = $this->getValidatedCalendar($calendarId);

        return [
            'name' => $calendar->get('name'),
            'description' => $calendar->get('description'),
            'duration' => $calendar->get('duration'),
            'maxBookingRange' => $calendar->get('maxBookingRange') ?? 30,
        ];
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Get and validate calendar
     * 
     * @throws NotFound
     */
    private function getValidatedCalendar(string $calendarId): Entity
    {
        $calendar = $this->calendarRepository->findCalendarById($calendarId);
        
        if (!$calendar) {
            throw new NotFound("Calendar not found");
        }

        if (!$calendar->get('isActive')) {
            throw new NotFound("Calendar not active");
        }

        return $calendar;
    }

    /**
     * Resolve team ID from coach identifier
     */
    private function resolveTeamFromCoach(?string $coachIdentifier): ?string
    {
        if (!$coachIdentifier) {
            return null;
        }

        $userId = $this->slugService->resolve('User', $coachIdentifier);
        if (!$userId) {
            return null;
        }

        $team = $this->entityManager
            ->getRDBRepository('CTeam')
            ->where(['assignedUserId' => $userId])
            ->findOne();

        return $team?->getId();
    }

    /**
     * Extract location IDs from availabilities
     */
    private function extractLocationIds(array $availabilities): array
    {
        $locationIds = [];
        foreach($availabilities as $availability) {
            if ($lid = $availability->get('locationId')) {
                $locationIds[] = $lid;
            }
        }
        return $locationIds;
    }

    /**
     * Normalize availabilities to array
     */
    private function normalizeAvailabilities($availabilities): array
    {
        if ($availabilities instanceof Entity) {
            return [$availabilities];
        }
        
        return is_array($availabilities) ? $availabilities : iterator_to_array($availabilities);
    }

    /**
     * Build calendar configuration for slot generation
     */
    private function buildCalendarConfig(Entity $calendar, string $dateString): array
    {
        $tzLocal = new DateTimeZone('Europe/Brussels');
        $tzUTC = new DateTimeZone('UTC');
        $now = new DateTime();
        $minLeadTime = $calendar->get('minLeadTime') ?? 0;
        
        $blockedByCalendarIds = $this->calendarRepository->getBlockedByCalendarIds($calendar);
        $relationshipBlockingAvailabilities = $this->calendarRepository->getBlockingCalendarAvailabilities($blockedByCalendarIds, $dateString);
        $relationshipBlockingMeetings = $this->calendarRepository->getBlockingCalendarMeetings($blockedByCalendarIds, $dateString);
        
        $allBlockingPeriods = array_merge(
            $relationshipBlockingAvailabilities,
            $relationshipBlockingMeetings
        );

        return [
            'calendar' => $calendar,
            'duration' => $calendar->get('duration'),
            'buffer' => $calendar->get('bufferTime'),
            'maxSeats' => $calendar->get('seatsPerMeeting'),
            'blockingPeriods' => $allBlockingPeriods,
            'bookings' => $this->calendarRepository->getBookingsForDate($calendar->getId(), $dateString),
            'firstBookableMoment' => (clone $now)->modify("+$minLeadTime hours"),
            'tzLocal' => $tzLocal,
            'tzUTC' => $tzUTC,
        ];
    }

    /**
     * Generate slots for a single availability
     */
    private function generateSlotsForAvailability(
        Entity $availability,
        string $dateString,
        array $config,
        ?array $locationData
    ): array {
        $startTime = $availability->get('startTime');
        $endTime = $availability->get('endTime');
        $duration = $config['duration'];
        $buffer = $config['buffer'];
        $maxSeats = $config['maxSeats'];
        $blockingPeriods = $config['blockingPeriods'];
        $bookings = $config['bookings'];
        $firstBookableMoment = $config['firstBookableMoment'];
        $tzLocal = $config['tzLocal'];

        $slots = [];
        $slotTime = DateTime::createFromFormat('H:i', $startTime, $tzLocal);
        $endTimeObj = DateTime::createFromFormat('H:i', $endTime, $tzLocal);

        while ($slotTime < $endTimeObj) {
            $slotStart = $slotTime->format('H:i');
            $slotEndTime = (clone $slotTime)->modify("+$duration minutes");
            $slotEnd = $slotEndTime->format('H:i');

            $isBlocked = $this->isSlotBlocked($slotStart, $slotEnd, $blockingPeriods);
            if (!$isBlocked) {
                $isTooSoon = $this->isSlotTooSoon($slotTime, $dateString, $firstBookableMoment);
                $availableSeats = $this->getAvailableSeats($slotStart, $maxSeats, $bookings);

                $isBookable = !$isBlocked && !$isTooSoon && $availableSeats > 0;
                $reason = $this->getUnavailabilityReason($isBlocked, $isTooSoon, $availableSeats);

                $slot = [
                    'start' => $slotStart,
                    'end' => $slotEnd,
                    'isBookable' => $isBookable,
                    'availableSeats' => $availableSeats,
                    'reason' => $reason,
                ];

                if ($locationData) {
                    $slot['locationId'] = $locationData['id'];
                    $slot['locationName'] = $locationData['name'];
                    $slot['locationAddressStreet'] = $locationData['addressStreet'];
                    $slot['locationAddressCity'] = $locationData['addressCity'];
                    $slot['locationAddressState'] = $locationData['addressState'];
                    $slot['locationAddressCountry'] = $locationData['addressCountry'];
                    $slot['locationAddressPostalCode'] = $locationData['addressPostalCode'];
                }

                $slots[] = $slot;
            }

            $slotTime->modify("+$duration minutes");
            if ($buffer > 0) {
                $slotTime->modify("+$buffer minutes");
            }
        }

        return $slots;
    }

    /**
     * Get unavailability reason for display
     */
    private function getUnavailabilityReason(bool $isBlocked, bool $isTooSoon, int $availableSeats): string
    {
        if ($isBlocked) {
            return 'Geblokkeerd';
        }
        if ($isTooSoon) {
            return 'Te kort op voorhand';
        }
        if ($availableSeats <= 0) {
            return 'Vol';
        }
        return '';
    }

    /**
     * Check if slot overlaps with blocking periods
     */
    private function isSlotBlocked(string $slotStart, string $slotEnd, array $blockingPeriods): bool
    {
        foreach ($blockingPeriods as $block) {
            $blockStart = $block['startTime'] ?? '';
            $blockEnd = $block['endTime'] ?? '';

            if (!$blockStart || !$blockEnd) {
                continue;
            }

            $blockBuffer = $block['_bufferTime'] ?? 0;
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

    /**
     * Check if slot is too soon based on minimum lead time
     */
    private function isSlotTooSoon(DateTime $slotTime, string $dateString, DateTime $firstBookableMoment): bool
    {
        $slotStartDateTime = new DateTime($dateString . ' ' . $slotTime->format('H:i'));
        return $slotStartDateTime < $firstBookableMoment;
    }

    /**
     * Calculate available seats for a time slot
     */
    private function getAvailableSeats(string $slotStart, int $maxSeats, array $bookings): int
    {
        $occupiedSeats = $bookings[$slotStart] ?? 0;
        return max(0, $maxSeats - $occupiedSeats);
    }

    /**
     * Deduplicate and sort slots by start time
     */
    private function deduplicateAndSortSlots(array $slots): array
    {
        usort($slots, fn($a, $b) => strcmp($a['start'], $b['start']));

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

    /**
     * Check if date is within booking range
     */
    private function isDateWithinRange(string $dateString, Entity $calendar): bool
    {
        $maxDays = $calendar->get('maxBookingRange') ?? 30;

        $today = new DateTime('today');
        $requestedDate = new DateTime($dateString);
        $maxDate = (clone $today)->modify("+$maxDays days");

        return $requestedDate >= $today && $requestedDate <= $maxDate;
    }
}