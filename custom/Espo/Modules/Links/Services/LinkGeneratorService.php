<?php

namespace Espo\Modules\Links\Services;

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
        $coachIdentifier = $user?->get('cSlug') ?: $team->get('id');

        return $this->generateLinks($coachIdentifier, $teamId);
    }

    public function getLinksForCenter(): array
    {
        return $this->generateLinks(null, null);
    }

    private function generateLinks(?string $coachIdentifier, ?string $teamId): array
    {
        $baseUrl = rtrim($this->config->get('siteUrl'), '/');
        $baseWidgetUrl = "$baseUrl/?entryPoint=widget";
        
        $coachParam = $coachIdentifier ? "&coach=$coachIdentifier" : "";

        $links = [];

        $widgets = [
            ['type' => 'survey', 'label' => 'Enquête', 'icon' => 'fas fa-clipboard-list'],
            ['type' => 'voucher', 'label' => 'Voucher', 'icon' => 'fas fa-ticket-alt'],
            ['type' => 'direct', 'label' => 'Direct Boeken', 'icon' => 'fas fa-bolt'],
            ['type' => 'referral', 'label' => 'Referral', 'icon' => 'fas fa-user-friends'],
        ];

        foreach ($widgets as $widget) {
            $links['widgets'][] = [
                'label' => $widget['label'],
                'url' => "{$baseWidgetUrl}&type={$widget['type']}{$coachParam}",
                'icon' => $widget['icon']
            ];
        }

        $calendars = $this->fetchCalendars($teamId);

        foreach ($calendars as $calendar) {
            $calendarIdentifier = $calendar->get('slug') ?: $calendar->get('id');
            $calName = $calendar->get('name');

            $links['calendars'][] = [
                'label' => "$calName (Algemeen)",
                'url' => "{$baseWidgetUrl}&type=calendar&id={$calendarIdentifier}{$coachParam}",
                'subtext' => 'Ongeacht locatie',
            ];

            $locations = $this->fetchLocationsForCalendar($calendar->get('id'), $teamId);

            foreach ($locations as $loc) {
                $links['calendars'][] = [
                    'label' => "$calName - {$loc['name']}",
                    'url' => "{$baseWidgetUrl}&type=calendar&id={$calendarIdentifier}{$coachParam}&location={$loc['slug']}",
                    'isLocation' => true
                ];
            }
        }

        return $links;
    }

    private function fetchCalendars(?string $teamId): iterable
    {
        $repo = $this->entityManager->getRDBRepository('CCalendar');
        
        $repo->where(['isActive' => true]);

        if ($teamId) {
            $repo->leftJoin('cTeams', 'teamJoin')
                 ->where([
                     'OR' => [
                         ['teamJoin.id' => $teamId],
                         ['teamJoin.id' => null]
                     ]
                 ]);
        }

        return $repo->distinct()->find();
    }

    private function fetchLocationsForCalendar(string $calendarId, ?string $teamId): array
    {
        $repo = $this->entityManager->getRDBRepository('CAvailability');
        
        $where = [
            'calendarId' => $calendarId,
            'deleted' => 0
        ];

        if ($teamId) {
            $where['OR'] = [
                ['teamId' => $teamId],
                ['teamId' => null]
            ];
        }

        $availabilities = $repo->where($where)->distinct()->find();

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

        $locations = $this->entityManager->getRDBRepository('CLocation')
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