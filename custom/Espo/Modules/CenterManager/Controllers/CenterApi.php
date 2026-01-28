<?php

namespace Espo\Modules\CenterManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Modules\Links\Services\LinkGeneratorService;

readonly class CenterApi
{
    public function __construct(
        private LinkGeneratorService $service
    ) {}

    public function getActionLinks(Request $request, Response $response): void
    {     
        $links = $this->service->getLinksForCenter();
        $response->writeBody(json_encode($links));
    }
}