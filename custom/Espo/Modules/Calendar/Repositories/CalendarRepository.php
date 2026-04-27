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
        ?string $teamId = null,
        ?string $publicSlug = null
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

        if ($publicSlug) {
            $criteria['publicSlug'] = $publicSlug;
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
     * Get IDs of calendars that block the given calendar
     */
    public function getBlockedByCalendarIds(Entity $calendar): array
    {
        $blockedByCalendars = $this->entityManager
            ->getRDBRepository('CCalendar')
            ->getRelation($calendar, 'blockedByCalendars')
            ->find();

        $ids = [];
        foreach ($blockedByCalendars as $blockingCalendar) {
            $ids[] = $blockingCalendar->getId();
        }

        return $ids;
    }

    /**
     * Get availabilities from blocking calendars for a specific date
     * 
     * @param array $calendarIds Array of blocking calendar IDs
     * @param string $dateString Date in Y-m-d format
     * @return array Array of availability data with buffer time
     */
    public function getBlockingCalendarAvailabilities(array $calendarIds, string $dateString): array
    {
        if (empty($calendarIds)) {
            return [];
        }

        $dayNum = (string) date('w', strtotime($dateString));

        $collection = $this->entityManager
            ->getRDBRepository('CAvailability')
            ->where([
                'calendarId' => $calendarIds,
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
     * Get meetings from blocking calendars for a specific date
     * 
     * @param array $calendarIds Array of blocking calendar IDs
     * @param string $dateString Date in Y-m-d format
     * @return array Array of meeting data with buffer time
     */
    public function getBlockingCalendarMeetings(array $calendarIds, string $dateString): array
    {
        if (empty($calendarIds)) {
            return [];
        }

        $meetings = $this->entityManager
            ->getRDBRepository('Meeting')
            ->where([
                'cCalendarId' => $calendarIds,
                'dateStart>=' => $dateString . ' 00:00:00',
                'dateStart<=' => $dateString . ' 23:59:59',
                'status!=' => ['Cancelled', 'Tentative']
            ])
            ->find();

        $result = [];

        foreach ($meetings as $meeting) {
            $startTime = (new \DateTime($meeting->get('dateStart')))->setTimezone(new DateTimeZone('Europe/Brussels'));
            $endTime = (new \DateTime($meeting->get('dateEnd')))->setTimezone(new DateTimeZone('Europe/Brussels'));
            
            $blockingCalId = $meeting->get('cCalendarId');
            $bufferTime = 0;
            
            if ($blockingCalId) {
                $blockingCal = $this->entityManager->getEntityById('CCalendar', $blockingCalId);
                $bufferTime = $blockingCal ? $blockingCal->get('bufferTime') : 0;
            }

            $result[] = [
                'startTime' => $startTime->format('H:i'),
                'endTime' => $endTime->format('H:i'),
                '_bufferTime' => $bufferTime,
                '_isMeeting' => true
            ];
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
                'isPubliek' => true,
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
            ->where([
                'isActive' => true,
                'isPubliek' => true,
            ])
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

    /**
     * Get public location/variant structure for a calendar and optional team.
     */
    public function getPublicLinkStructureForCalendar(string $calendarId, ?string $teamId = null): array
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

        $locationRows = [];
        $variantBuckets = [];

        foreach ($availabilities as $availability) {
            $locationKey = $availability->get('locationId') ?: '__none__';

            if (!isset($locationRows[$locationKey])) {
                $locationRows[$locationKey] = [
                    'id' => $availability->get('locationId'),
                    'name' => null,
                    'slug' => null,
                    'variants' => [],
                ];
            }

            $publicSlug = trim((string) ($availability->get('publicSlug') ?? ''));
            if ($publicSlug === '') {
                continue;
            }

            $variantKey = $locationKey . '::' . $publicSlug;
            if (!isset($variantBuckets[$variantKey])) {
                $variantBuckets[$variantKey] = [
                    'locationKey' => $locationKey,
                    'slug' => $publicSlug,
                    'label' => $availability->get('name') ?: $publicSlug,
                    'description' => $availability->get('description') ?: null,
                ];
            }

            if (!$variantBuckets[$variantKey]['description'] && $availability->get('description')) {
                $variantBuckets[$variantKey]['description'] = $availability->get('description');
            }
        }

        $locationIds = array_values(array_filter(array_map(
            static fn(array $row) => $row['id'],
            $locationRows
        )));

        if (!empty($locationIds)) {
            $locations = $this->entityManager
                ->getRDBRepository('CLocation')
                ->where(['id' => array_unique($locationIds)])
                ->order('name')
                ->find();

            foreach ($locations as $location) {
                $locationRows[$location->getId()]['name'] = $location->get('name');
                $locationRows[$location->getId()]['slug'] = $location->get('slug') ?: $location->getId();
            }
        }

        foreach ($variantBuckets as $variant) {
            $locationRows[$variant['locationKey']]['variants'][] = [
                'slug' => $variant['slug'],
                'label' => $variant['label'],
                'description' => $variant['description'],
            ];
        }

        $result = array_values($locationRows);

        usort($result, static function (array $a, array $b): int {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        foreach ($result as &$row) {
            usort($row['variants'], static function (array $a, array $b): int {
                return strcmp($a['label'], $b['label']);
            });
        }

        return $result;
    }

    /**
     * Find a public variant configuration for calendar/team filters.
     */
    public function findPublicVariant(
        string $calendarId,
        string $publicSlug,
        ?string $locationId = null,
        ?string $teamId = null
    ): ?Entity {
        $where = [
            'calendarId' => $calendarId,
            'publicSlug' => $publicSlug,
            'deleted' => 0,
        ];

        if ($locationId) {
            $where['locationId'] = $locationId;
        }

        $query = $this->entityManager
            ->getRDBRepository('CAvailability')
            ->leftJoin('cTeams', 'teamJoin')
            ->where($where);

        if ($teamId) {
            $query = $query->where([
                'OR' => [
                    ['teamJoin.id' => $teamId],
                    ['teamJoin.id' => null]
                ]
            ]);
        }

        return $query->findOne();
    }
}
