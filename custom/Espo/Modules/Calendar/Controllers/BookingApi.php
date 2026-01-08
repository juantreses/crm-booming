<?php

namespace Espo\Modules\Calendar\Controllers;

use Espo\Core\Api\Request;
use Espo\Modules\Calendar\Services\BookingService;

readonly class BookingApi
{
    public function __construct(
        private BookingService $bookingService
    ){}

    public function postAction(Request $request): array
    {
        $data = $request->getParsedBody();

        if (empty($data['calendarId']) || empty($data['email'])) {
            throw new BadRequest("Onvoldoende gegevens voor de boeking.");
        }

        return $this->bookingService->processBooking((array)$data);
    }
}