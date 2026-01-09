<?php

namespace Espo\Modules\Booking\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Modules\Booking\Services\BookingService;

readonly class BookingApi
{
    public function __construct(
        private BookingService $bookingService
    ){}

    /**
     * @throws BadRequest
     * @throws Conflict
     */
    public function postActionBooking(Request $request): array
    {
        $data = $request->getParsedBody();

        if (empty($data->calendarId) || empty($data->email)) {
            throw new BadRequest("Onvoldoende gegevens voor de boeking.");
        }

        return $this->bookingService->processBooking((array)$data);
    }
}