<?php

namespace Espo\Modules\Calendar\Repositories;

use DateTimeZone;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\SthCollection;

/**
 * Repository for calendar data access
 * Handles all database queries related to calendars, availabilities, and locations
 */
readonly class CalendarRepository
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function findCalendarById(string $calendarId): ?Entity
    {
        return $this->entityManager->getEntityById('CCalendar', $calendarId);
    }

    public function getBookableCalendars(): EntityCollection
    {
        return $this->entityManager
            ->getRDBRepository('CCalendar')
            ->where([
                'isActive' => true,
                'isDirectBookable' => true,
                'deleted' => false,
            ])
            ->find();
    }

    /**
     * Get availabilities for a specific date
     * 
     * @param string $calendarId
     * @param string $dateString Date in Y-m-d format
     * @param string|null $locationId Optional location filter
     * @param string|null $teamId Optional team filter
     */
    public function getAvailabilitiesForDate(
        string $calendarId,
        string $dateString,
        ?string $locationId = null,
        ?string $teamId = null
    ): SthCollection|EntityCollection {
        $dayNum = (string) date('w', strtotime($dateString));

        $criteria = [
            'calendarId' => $calendarId,
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

        $repo = $this->entityManager->getRDBRepository('CAvailability');

        if ($teamId) {
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

        return $repo->where($criteria)->find();
    }

    /**
     * Get blocking availabilities from higher priority calendars
     */
    public function getBlockingAvailabilities(Entity $calendar, string $dateString): array
    {
        $collection = $this->entityManager
            ->getRDBRepository('CAvailability')
            ->join('calendar')
            ->where([
                'calendarId!=' => $calendar->get('id'),
                'type' => 'specific',
                'date' => $dateString,
                'calendar.priority>' => $calendar->get('priority'),
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
     * Get existing bookings for a calendar on a specific date
     * Returns array keyed by time string (H:i) with booking counts
     */
    public function getBookingsForDate(string $calendarId, string $dateString): array
    {
        $bookingList = $this->entityManager
            ->getRDBRepository('Meeting')
            ->where([
                'cCalendarId' => $calendarId,
                'dateStart>=' => $dateString . ' 00:00:00',
                'dateStart<=' => $dateString . ' 23:59:59',
                'status!=' => ['Cancelled', 'Tentative']
            ])
            ->find();

        $counts = [];

        foreach ($bookingList as $booking) {
            $startTime = (new \DateTime($booking->get('dateStart')))->setTimezone(new DateTimeZone('Europe/Brussels'));
            $timeKey = $startTime->format('H:i');

            if (!isset($counts[$timeKey])) {
                $counts[$timeKey] = 0;
            }

            $counts[$timeKey]++;
        }

        return $counts;
    }

    /**
     * Get locations by IDs with formatted data
     */
    public function getLocationsByIds(array $locationIds): array
    {
        if (empty($locationIds)) {
            return [];
        }

        $locations = $this->entityManager
            ->getRDBRepository('CLocation')
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

    /**
     * Fetch calendars for a specific team
     * Used by Links module
     */
    public function getCalendarsForTeam(string $teamId): EntityCollection
    {
        $availabilities = $this->entityManager
            ->getRDBRepository('CAvailability')
            ->leftJoin('cTeams', 'teamJoin')
            ->where([
                'deleted' => 0,
                'OR' => [
                    ['teamJoin.id' => $teamId],
                    ['teamJoin.id' => null]
                ]
            ])
            ->distinct()
            ->find();

        $calendarIds = [];
        foreach ($availabilities as $availability) {
            if ($calId = $availability->get('calendarId')) {
                $calendarIds[] = $calId;
            }
        }

        $calendarIds = array_unique($calendarIds);

        if (empty($calendarIds)) {
            return new EntityCollection();
        }

        return $this->entityManager
            ->getRDBRepository('CCalendar')
            ->where([
                'isActive' => true,
                'id' => $calendarIds
            ])
            ->order('name')
            ->find();
    }

    /**
     * Fetch all active calendars
     */
    public function getActiveCalendars(): EntityCollection
    {
        return $this->entityManager
            ->getRDBRepository('CCalendar')
            ->where(['isActive' => true])
            ->find();
    }

    /**
     * Get locations available for a calendar and optional team
     */
    public function getLocationsForCalendar(string $calendarId, ?string $teamId = null): array
    {
        $where = [
            'calendarId' => $calendarId,
            'deleted' => 0
        ];

        if ($teamId) {
            $where['OR'] = [
                ['teamJoin.id' => $teamId],
                ['teamJoin.id' => null]
            ];
        }

        $availabilities = $this->entityManager
            ->getRDBRepository('CAvailability')
            ->leftJoin('cTeams', 'teamJoin')
            ->where($where)
            ->distinct()
            ->find();

        if (count($availabilities) === 0) {
            return [];
        }

        $locationIds = [];
        foreach ($availabilities as $availability) {
            if ($locId = $availability->get('locationId')) {
                $locationIds[] = $locId;
            }
        }
        
        $locationIds = array_unique($locationIds);

        if (empty($locationIds)) {
            return [];
        }

        $locations = $this->entityManager
            ->getRDBRepository('CLocation')
            ->where(['id' => $locationIds])
            ->order('name')
            ->find();

        $result = [];
        foreach ($locations as $loc) {
            $result[] = [
                'name' => $loc->get('name'),
                'slug' => $loc->get('slug') ?: $loc->get('id')
            ];
        }

        return $result;
    }
}