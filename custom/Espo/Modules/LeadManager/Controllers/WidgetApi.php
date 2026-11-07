<?php

namespace Espo\Modules\LeadManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Modules\LeadManager\Services\LeadFromWidgetService;

class WidgetApi
{
    public function __construct(
        private readonly LeadFromWidgetService $leadFromWidgetService,
    ) {
    }

    public function postActionSubmit(Request $request): array
    {
        $data = $request->getParsedBody();

        $dataArray = json_decode(json_encode($data), true);

        return $this->leadFromWidgetService->createFromSubmission($dataArray);
    }
}
