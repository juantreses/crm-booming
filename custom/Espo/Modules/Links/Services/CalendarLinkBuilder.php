<?php

namespace Espo\Modules\Links\Services;

use Espo\Core\Utils\Config;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Links\ValueObjects\CalendarLink;
use Espo\ORM\EntityCollection;

/**
 * Builds calendar booking links
 */
readonly class CalendarLinkBuilder
{
    public function __construct(
        private Config $config,
        private CalendarRepository $calendarRepository,
    ) {}

    /**
     * Build calendar links for a team
     * 
     * @param string $teamId
     * @param string|null $coachIdentifier
     * @return CalendarLink[]
     */
    public function buildForTeam(string $teamId, ?string $coachIdentifier): array
    {
        $calendars = $this->calendarRepository->getCalendarsForTeam($teamId);
        return $this->buildLinksFromCalendars($calendars, $teamId, $coachIdentifier);
    }

    /**
     * Build calendar links for center (all active calendars)
     * 
     * @return CalendarLink[]
     */
    public function buildForCenter(): array
    {
        $calendars = $this->calendarRepository->getActiveCalendars();
        return $this->buildLinksFromCalendars($calendars, null, null);
    }

    /**
     * Build calendar links from calendar entities
     * 
     * @param EntityCollection $calendars
     * @param string|null $teamId
     * @param string|null $coachIdentifier
     * @return CalendarLink[]
     */
    private function buildLinksFromCalendars(EntityCollection $calendars, ?string $teamId, ?string $coachIdentifier): array
    {
        $links = [];
        $baseUrl = rtrim($this->config->get('siteUrl'), '/');
        $baseWidgetUrl = "$baseUrl/?entryPoint=widget";
        $coachParam = $coachIdentifier ? "&coach=$coachIdentifier" : "";

        foreach ($calendars as $calendar) {
            $calendarIdentifier = $calendar->get('slug') ?: $calendar->get('id');
            $calendarName = $calendar->get('name');
            $calendarId = $calendar->get('id');
            $baseCalendarUrl = "{$baseWidgetUrl}&type=calendar&id={$calendarIdentifier}{$coachParam}";
            $locationStructure = $this->calendarRepository->getPublicLinkStructureForCalendar($calendarId, $teamId);
            $locationCount = count(array_filter($locationStructure, fn(array $row) => !empty($row['id'])));
            $hasVariants = count(array_filter(
                $locationStructure,
                fn(array $row) => count($row['variants']) > 0
            )) > 0;

            if ($locationCount < 2 && !$hasVariants) {
                $links[] = new CalendarLink(
                    label: $calendarName,
                    url: $baseCalendarUrl,
                );
            } else {
                $links[] = new CalendarLink(
                    label: $calendarName,
                    url: $baseCalendarUrl,
                    subtext: $locationCount > 1 ? 'Alle locaties' : 'Alle sessies'
                );

                foreach ($locationStructure as $location) {
                    $locationUrl = $baseCalendarUrl;

                    if (!empty($location['slug'])) {
                        $locationUrl .= "&location={$location['slug']}";

                        $links[] = new CalendarLink(
                            label: "{$calendarName} - {$location['name']}",
                            url: $locationUrl,
                            isLocation: true
                        );
                    }

                    foreach ($location['variants'] as $variant) {
                        $links[] = new CalendarLink(
                            label: $variant['label'],
                            url: "{$locationUrl}&variant={$variant['slug']}",
                            isVariant: true
                        );
                    }
                }
            }
        }

        return $links;
    }
}
