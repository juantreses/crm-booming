<?php

namespace Espo\Modules\Calendar\Controllers;


use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Log;
use Espo\Modules\Calendar\Services\CalendarSlotService;
use Exception;

readonly class CalendarApi
{
    public function __construct(
        private Log                 $log,
        private CalendarSlotService $calendarSlotService,
    ) {}

    public function getActionSlots(Request $request)
    {
        try {
            $id = $request->getRouteParam('id');
            $date = $request->getQueryParam('date') ?? date('Y-m-d');

            if (!$id) {
                throw new BadRequest("Missing required param id");
            }

            return $this->calendarSlotService->getAvailableSlots($id, $date);

        }  catch (BadRequest $e) {
            // Return proper error response
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (Exception $e) {
            // Log unexpected errors
            $this->log->error('Calendar API Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }
}