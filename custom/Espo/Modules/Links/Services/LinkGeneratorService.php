<?php

namespace Espo\Modules\Links\Services;

use Espo\ORM\EntityManager;
use Espo\Modules\Links\ValueObjects\LinkCollection;

readonly class LinkGeneratorService
{
    public function __construct(
        private EntityManager $entityManager,
        private WidgetLinkBuilder $widgetLinkBuilder,
        private CalendarLinkBuilder $calendarLinkBuilder,
    ) {}

    /**
     * Generate all links for a specific team
     * 
     * @param string $teamId
     * @return LinkCollection
     */
    public function getLinksForTeam(string $teamId): LinkCollection
    {
        $team = $this->entityManager->getEntityById('CTeam', $teamId);
        if (!$team) {
            return new LinkCollection([], []);
        }

        $coachIdentifier = $this->getCoachIdentifier($team);

        $widgetLinks = $this->widgetLinkBuilder->build($coachIdentifier);
        $calendarLinks = $this->calendarLinkBuilder->buildForTeam($teamId, $coachIdentifier);

        return new LinkCollection($widgetLinks, $calendarLinks);
    }

    /**
     * Generate all links for the center (no specific team/coach)
     * 
     * @return LinkCollection
     */
    public function getLinksForCenter(): LinkCollection
    {
        $widgetLinks = $this->widgetLinkBuilder->build(null);
        $calendarLinks = $this->calendarLinkBuilder->buildForCenter();

        return new LinkCollection($widgetLinks, $calendarLinks);
    }

    /**
     * Get the coach identifier (slug) from team
     * Falls back to team ID if no slug is set
     * 
     * @param \Espo\ORM\Entity $team
     * @return string|null
     */
    private function getCoachIdentifier($team): ?string
    {
        $assignedUserId = $team->get('assignedUserId');
        if (!$assignedUserId) {
            return $team->get('id');
        }

        $user = $this->entityManager->getEntityById('User', $assignedUserId);
        if (!$user) {
            return $team->get('id');
        }

        return $user->get('cSlug') ?: $team->get('id');
    }
}