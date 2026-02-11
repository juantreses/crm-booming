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

            $locations = $this->calendarRepository->getLocationsForCalendar($calendarId, $teamId);

            if (count($locations) < 2) {
                $links[] = new CalendarLink(
                    label: $calendarName,
                    url: "{$baseWidgetUrl}&type=calendar&id={$calendarIdentifier}{$coachParam}",
                );
            } else {
                $links[] = new CalendarLink(
                    label: $calendarName,
                    url: "{$baseWidgetUrl}&type=calendar&id={$calendarIdentifier}{$coachParam}",
                    subtext: 'Alle locaties'
                );

                foreach ($locations as $location) {
                    $links[] = new CalendarLink(
                        label: "{$calendarName} - {$location['name']}",
                        url: "{$baseWidgetUrl}&type=calendar&id={$calendarIdentifier}{$coachParam}&location={$location['slug']}",
                        isLocation: true
                    );
                }
            }
        }

        return $links;
    }
}