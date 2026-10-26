<?php

namespace Espo\Modules\Booking\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Booking\Services\BookingService;
use Espo\Modules\Booking\Services\MeetingCancellationService;

readonly class BookingApi
{
    public function __construct(
        private BookingService $bookingService,
        private MeetingCancellationService $meetingCancellationService
    ){}

    /**
     * @throws BadRequest
     * @throws Conflict
     * @throws NotFound
     */
    public function postActionBooking(Request $request): array
    {
        $data = $request->getParsedBody();

        if (empty($data->calendarId) || empty($data->email)) {
            throw new BadRequest("Onvoldoende gegevens voor de boeking.");
        }

        return $this->bookingService->processBooking((array)$data);
    }

    /**
     * @throws BadRequest
     * @throws Conflict
     * @throws NotFound
     */
    public function postActionBookWorkout(Request $request): array
    {
        $data = $request->getParsedBody();

        if (empty($data->entityType) || empty($data->entityId)) {
            throw new BadRequest("Onvoldoende gegevens voor de boeking.");
        }

        if (empty($data->selectedDate) || empty($data->selectedTime)) {
            throw new BadRequest("Geen tijdstip geselecteerd.");
        }

        return $this->bookingService->bookWorkout((array) $data);
    }

    /**
     * @throws BadRequest
     * @throws Conflict
     * @throws NotFound
     */
    public function postActionBookFollowUp(Request $request): array
    {
        $data = $request->getParsedBody();

        if (empty($data->entityType) || empty($data->entityId)) {
            throw new BadRequest("Onvoldoende gegevens voor de boeking.");
        }

        if (empty($data->selectedDate) || empty($data->selectedTime)) {
            throw new BadRequest("Geen tijdstip geselecteerd.");
        }

        return $this->bookingService->bookFollowUp((array) $data);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function postActionCancelMeeting(Request $request): array
    {
        $data = $request->getParsedBody();

        if (empty($data->id)) {
            throw new BadRequest("Geen afspraak opgegeven.");
        }

        return $this->meetingCancellationService->cancelAndRemove($data->id);
    }
}
