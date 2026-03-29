<?php

namespace Espo\Modules\LeadManager\Validators;

use Espo\Core\Exceptions\BadRequest;

class LogKickstartBookingValidator
{
    public function validate(\StdClass $data): void
    {
        if (!isset($data->id) || empty($data->id)) {
            throw new BadRequest('Lead ID is required.');
        }

        if (empty($data->calendarId)) {
            throw new BadRequest('Geen agenda geselecteerd.');
        }

        if (!isset($data->selectedDate, $data->selectedTime)) {
            throw new BadRequest('Geen tijdstip geselecteerd.');
        }
    }
}
