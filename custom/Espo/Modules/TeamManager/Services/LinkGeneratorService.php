<?php

namespace Espo\Modules\TeamManager\Services;

use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;

readonly class LinkGeneratorService
{
    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
    ) {}

    public function getLinksForTeam(string $teamId): array
    {
        $team = $this->entityManager->getEntityById('CTeam', $teamId);
        if (!$team) {
            return [];
        }

        $user = $this->entityManager->getEntityById('User', $team->get('assignedUserId'));
        $coachIdentifier = null;
        $coachIdentifier = $user?->get('cSlug') ?: $team->get('id');

        $baseUrl = rtrim($this->config->get('siteUrl'), '/');
        $baseWidgetUrl = "$baseUrl/?entryPoint=widget";
        $links = [];

        $links['widgets'] = [
            ['label' => 'Intake / Enquête', 'url' => "$baseWidgetUrl&type=survey&coach=$coachIdentifier", 'icon' => 'fas fa-clipboard-list'],
            ['label' => 'Direct Boeken', 'url' => "$baseWidgetUrl&type=direct&coach=$coachIdentifier", 'icon' => 'fas fa-bolt'],
            ['label' => 'Referral', 'url' => "$baseWidgetUrl&type=referral&coach=$coachIdentifier", 'icon' => 'fas fa-user-friends']
        ];

        $calendars = $this->entityManager->getRDBRepository('CCalendar')
            ->leftJoin('cTeams', 'teamJoin') 
            ->where([
                'isActive' => true,
                'OR' => [
                    ['teamJoin.id' => $teamId],
                    ['teamJoin.id' => null]
                ]
            ])
            ->distinct()
            ->find();

        foreach ($calendars as $calendar) {
            $calendarIdentifier = $calendar->get('slug') ?: $calendar->get('id');
            $calName = $calendar->get('name');

            $links['calendars'][] = [
                'label' => "$calName (Algemeen)",
                'url' => "$baseWidgetUrl&type=calendar&id=$calendarIdentifier&coach=$coachIdentifier",
                'subtext' => 'Ongeacht locatie',
            ];

            $locations = $this->getLocationsForCalendarAndTeam($calendar->get('id'), $teamId);

            foreach ($locations as $loc) {
                $links['calendars'][] = [
                    'label' => "$calName - {$loc['name']}",
                    'url' => "$baseWidgetUrl&type=calendar&id=$calendarIdentifier&coach=$coachIdentifier&location={$loc['locationIdentifier']}",
                    'isLocation' => true
                ];
            }
        }

        return $links;
    }


    private function getLocationsForCalendarAndTeam(string $calendarId, string $teamId): array
    {
        $availabilities = $this->entityManager->getRDBRepository('CAvailability')
            ->leftJoin('cTeams', 'teamJoin')
            ->where([
                'calendarId' => $calendarId,
                'deleted' => 0,
                'OR' => [
                    ['teamJoin.id' => $teamId],
                    ['teamJoin.id' => null]
                ]
            ])
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

        if (empty($locationIds)) {
            return [];
        }

        $locations = $this->entityManager->getRDBRepository('CLocation')
            ->where(['id' => array_unique($locationIds)])
            ->order('name')
            ->find();

        $result = [];
        foreach ($locations as $loc) {
            $locationIdentifier = $loc->get('slug') ?: $loc->get('id');
            $result[] = [
                'name' => $loc->get('name'),
                'locationIdentifier' => $locationIdentifier
            ];
        }

        return $result;
    }
}