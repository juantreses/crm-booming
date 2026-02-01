<?php

namespace Espo\Modules\Calendar\Controllers;


use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Log;
use Espo\Modules\Calendar\Services\CalendarService;
use Exception;

readonly class CalendarApi
{
    public function __construct(
        private Log             $log,
        private CalendarService $calendarService,
    ) {}

    public function getActionSettings(Request $request): array
    {
        try {
            $id = $request->getRouteParam('id');

            if (!$id) {
                throw new BadRequest("Missing required param id");
            }

            return $this->calendarService->getSettings($id);

        }  catch (BadRequest $e) {
            // Return proper error response
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (NotFound $e) {
            // Return proper error response
            http_response_code(404);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 404
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

    public function getActionSlots(Request $request)
    {
        try {
            $id = $request->getRouteParam('id');
            $date = $request->getQueryParam('date') ?? date('Y-m-d');
            $location = $request->getQueryParam('location');

            if (!$id) {
                throw new BadRequest("Missing required param id");
            }

            return $this->calendarService->getAvailableSlots($id, $date, $location);

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

    public function getActionAvailability(Request $request): array
    {
        try {
            $id = $request->getRouteParam('id');
            $year = $request->getQueryParam('year') ?? date('Y');
            $month = $request->getQueryParam('month') ?? date('m');
            $location = $request->getQueryParam('location');

            if (!$id) {
                throw new BadRequest("Missing required param id");
            }

            return $this->calendarService->getMonthAvailability($id, (int)$year, (int)$month, $location);

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

    public function getActionBookableList(): array
    {
        return $this->calendarService->getBookableCalendars();
    }

    public function getActionUpcomingSlots(Request $request): array
    {
        $id = $request->getQueryParam('id');
        
        if (!$id) {
            throw new BadRequest("ID parameter is verplicht");
        }
        return $this->calendarService->getUpcomingSlots($id);
    }
}