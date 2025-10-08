<?php

namespace Espo\Custom\Validators;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\KickstartOutcome;

class LogKickstartValidator
{
    public function validate(\StdClass $data): void
    {
        if (!isset($data->id) || empty($data->id)) {
            throw new BadRequest('Lead ID is required.');
        }

        $outcome = $data->outcome ?? null;
        if (!$outcome || !KickstartOutcome::isValid($outcome)) {
            throw new BadRequest('Invalid call outcome.');
        }

        $timezone = new \DateTimeZone('Europe/Brussels');

        if (isset($data->kickstartDateTime) && !empty($data->kickstartDateTime)) {
            if (new \DateTime($data->kickstartDateTime, $timezone) > new \DateTime('now', $timezone)) {
                throw new BadRequest('Kickstart datum/tijd mag niet in de toekomst zijn.');
            }
        }

        if ($outcome === KickstartOutcome::STILL_THINKING->value) {
            if (!isset($data->callAgainDateTime) || empty($data->callAgainDateTime)) {
                throw new BadRequest('Datum/tijd opnieuw bellen is verplicht.');
            }

            if (new \DateTime($data->callAgainDateTime, $timezone) <= new \DateTime('now', $timezone)) {
                throw new BadRequest('atum/tijd opnieuw bellen moet in de toekomst zijn.');
            }
        }
    }
}