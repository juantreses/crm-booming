<?php

namespace Espo\Modules\CenterManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Modules\Links\Services\LinkGeneratorService;

readonly class CenterApi
{
    public function __construct(
        private LinkGeneratorService $linkGeneratorService,
    ) {}

    /**
     * GET /api/v1/center/links
     * 
     * Returns widget and calendar links for the entire center
     */
    public function getActionLinks(Request $request): array
    {
        $linkCollection = $this->linkGeneratorService->getLinksForCenter();
        
        return $linkCollection->toArray();
    }
}