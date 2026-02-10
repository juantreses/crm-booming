<?php

namespace Espo\Modules\Links\Services;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * Repository for fetching calendars and their locations
 */
readonly class CalendarRepository
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * Fetch calendars for a specific team
     * 
     * @param string $teamId
     * @return EntityCollection Active calendars that have availabilities for this team (or center-wide)
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
     * Fetch all active calendars (for center-wide links)
     * 
     * @return EntityCollection
     */
    public function getActiveCalendars(): EntityCollection
    {
        return $this->entityManager
            ->getRDBRepository('CCalendar')
            ->where(['isActive' => true])
            ->find();
    }

    /**
     * Get locations available for a calendar and team
     * 
     * @param string $calendarId
     * @param string|null $teamId Filter by team if provided
     * @return array Array of ['name' => string, 'slug' => string]
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