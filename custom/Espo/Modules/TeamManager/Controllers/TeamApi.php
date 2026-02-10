<?php

namespace Espo\Modules\TeamManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Modules\Links\Services\LinkGeneratorService;

readonly class TeamApi
{
    public function __construct(
        private LinkGeneratorService $linkGeneratorService,
    ) {}

    /**
     * GET /api/v1/team/{id}/links
     * 
     * Returns widget and calendar links for a specific team
     */
    public function getActionLinks(Request $request): array
    {
        $teamId = $request->getRouteParam('id');
        
        if (!$teamId) {
            return ['widgets' => [], 'calendars' => []];
        }

        $linkCollection = $this->linkGeneratorService->getLinksForTeam($teamId);
        
        return $linkCollection->toArray();
    }
}