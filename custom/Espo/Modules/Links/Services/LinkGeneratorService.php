<?php

namespace Espo\Modules\Links\Services;

use Espo\ORM\Entity;
use Espo\Modules\Links\ValueObjects\LinkCollection;
use Espo\ORM\EntityManager;

readonly class LinkGeneratorService
{
    public function __construct(
        private EntityManager $entityManager,
        private WidgetLinkBuilder $widgetLinkBuilder,
        private CalendarLinkBuilder $calendarLinkBuilder,
    ) {
    }

    /**
     * Generate all links for a specific team
     */
    public function getLinksForTeam(string $teamId): LinkCollection
    {
        $team = $this->entityManager->getEntityById('CTeam', $teamId);
        if (!$team instanceof Entity) {
            return new LinkCollection([], []);
        }

        $coachIdentifier = $this->getCoachIdentifier($team);

        $widgetLinks = $this->widgetLinkBuilder->build($coachIdentifier);
        $calendarLinks = $this->calendarLinkBuilder->buildForTeam($teamId, $coachIdentifier);

        return new LinkCollection($widgetLinks, $calendarLinks);
    }

    /**
     * Generate all links for the center (no specific team/coach)
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
     */
    private function getCoachIdentifier(Entity $team): ?string
    {
        $assignedUserId = $team->get('assignedUserId');
        if (!$assignedUserId) {
            return $team->get('id');
        }

        $user = $this->entityManager->getEntityById('User', $assignedUserId);
        if (!$user instanceof Entity) {
            return $team->get('id');
        }

        return $user->get('cSlug') ?: $team->get('id');
    }
}
