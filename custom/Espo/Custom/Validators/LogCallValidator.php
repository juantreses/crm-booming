<?php

namespace Espo\Custom\Validators;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\CallOutcome;

class LogCallValidator
{
    public function validate(\StdClass $data): void
    {
        if (!isset($data->id) || empty($data->id)) {
            throw new BadRequest('Lead ID is required.');
        }

        $outcome = $data->outcome ?? null;
        if (!$outcome || !CallOutcome::isValid($outcome)) {
            throw new BadRequest('Invalid call outcome.');
        }

        $timezone = new \DateTimeZone('Europe/Brussels');

        if (isset($data->callDateTime) && !empty($data->callDateTime)) {
            if (new \DateTime($data->callDateTime, $timezone) > new \DateTime('now', $timezone)) {
                throw new BadRequest('Gesprek datum/tijd mag niet in de toekomst zijn.');
            }
        }

        if ($outcome === CallOutcome::CALL_AGAIN->value) {
            if (!isset($data->callAgainDateTime) || empty($data->callAgainDateTime)) {
                throw new BadRequest('Datum/tijd opnieuw bellen is verplicht.');
            }

            if (new \DateTime($data->callAgainDateTime, $timezone) <= new \DateTime('now', $timezone)) {
                throw new BadRequest('Datum/tijd opnieuw bellen moet in de toekomst zijn.');
            }
        }
    }
}