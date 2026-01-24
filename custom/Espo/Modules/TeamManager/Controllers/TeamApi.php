<?php

namespace Espo\Modules\TeamManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Modules\TeamManager\Services\LinkGeneratorService;

readonly class TeamApi
{
    public function __construct(
        private LinkGeneratorService $service
    ) {}

    public function getActionLinks(Request $request, Response $response): void
    {
        $teamId = $request->getRouteParam('id');
        $links = $this->service->getLinksForTeam($teamId);
        $response->writeBody(json_encode($links));
    }
}