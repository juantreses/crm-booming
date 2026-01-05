<?php

namespace Espo\Custom\EntryPoint;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\EntryPoint\EntryPoint;
use Espo\Core\EntryPoint\Traits\NoAuth;
use Espo\Modules\Calendar\Services\CalendarSlotService;

class Calendar implements EntryPoint
{
    public function __construct(
        private readonly CalendarSlotService $calendarSlotService,
    )
    {}

    use NoAuth;

    public function run(Request $request, Response $response): void
    {
        //$calendarId = $request->getRouteParam('id');
        //$date = $request->getQueryParam('date');

        if (!$calendarId || !$date) {
            throw new BadRequest("ID en Datum zijn verplicht.");
        }

        try {
            $slots = $this->calendarSlotService->getAvailableSlots($calendarId, $date);

            // Stuur de headers en JSON terug
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'date' => $date,
                'slots' => $slots
            ]);
            exit;
        } catch (\Exception $e) {
            throw new BadRequest($e->getMessage());
        }
    }
}