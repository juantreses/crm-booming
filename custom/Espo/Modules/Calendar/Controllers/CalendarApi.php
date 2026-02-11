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
        private Log $log,
        private CalendarService $calendarService,
    ) {}

    /**
     * Get calendar settings
     */
    public function getActionSettings(Request $request): array
    {
        try {
            $id = $request->getRouteParam('id');

            if (!$id) {
                throw new BadRequest("Missing required parameter: id");
            }

            return $this->calendarService->getSettings($id);

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (NotFound $e) {
            http_response_code(404);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 404
            ];
        } catch (Exception $e) {
            $this->log->error('Calendar API Error (getSettings): ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }

    /**
     * Get available slots for a date
     */
    public function getActionSlots(Request $request): array
    {
        try {
            $id = $request->getRouteParam('id');
            
            if (!$id) {
                throw new BadRequest("Missing required parameter: id");
            }

            $date = $request->getQueryParam('date') ?? date('Y-m-d');
            $location = $request->getQueryParam('location');
            $coach = $request->getQueryParam('coach');

            return $this->calendarService->getAvailableSlots(
                $id,
                $date,
                $location,
                $coach
            );

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (NotFound $e) {
            http_response_code(404);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 404
            ];
        } catch (Exception $e) {
            $this->log->error('Calendar API Error (getSlots): ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }

    /**
     * Get month availability
     */
    public function getActionAvailability(Request $request): array
    {
        try {
            $id = $request->getRouteParam('id');
            
            if (!$id) {
                throw new BadRequest("Missing required parameter: id");
            }

            $year = $request->getQueryParam('year') ?? date('Y');
            $month = $request->getQueryParam('month') ?? date('m');
            $location = $request->getQueryParam('location');
            $coach = $request->getQueryParam('coach');

            return $this->calendarService->getMonthAvailability(
                $id,
                (int)$year,
                (int)$month,
                $location,
                $coach
            );

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (NotFound $e) {
            http_response_code(404);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 404
            ];
        } catch (Exception $e) {
            $this->log->error('Calendar API Error (getAvailability): ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }

    /**
     * Get bookable calendars list
     */
    public function getActionBookableList(): array
    {
        try {
            return $this->calendarService->getBookableCalendars();
            
        } catch (Exception $e) {
            $this->log->error('Calendar API Error (getBookableList): ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }

    /**
     * Get upcoming slots
     */
    public function getActionUpcomingSlots(Request $request): array
    {
        try {
            $id = $request->getQueryParam('id');
            
            if (!$id) {
                throw new BadRequest("Missing required parameter: id");
            }

            $coach = $request->getQueryParam('coach');
            
            return $this->calendarService->getUpcomingSlots($id, $coach);

        } catch (BadRequest $e) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (NotFound $e) {
            http_response_code(404);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 404
            ];
        } catch (Exception $e) {
            $this->log->error('Calendar API Error (getUpcomingSlots): ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }
    }
}