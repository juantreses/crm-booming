<?php

namespace Espo\Modules\LeadManager\Validators;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\IntroMeetingOutcome;

class LogIntroMeetingValidator
{
    public function validate(\StdClass $data): void
    {
        if (!isset($data->id) || empty($data->id)) {
            throw new BadRequest('Lead ID is required.');
        }

        $outcome = $data->outcome ?? null;
        if (!$outcome || !IntroMeetingOutcome::isValid($outcome)) {
            throw new BadRequest('Invalid intro meeting outcome.');
        }

        if (!empty($data->introDateTime)) {
            $introDate = new \DateTime($data->introDateTime);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));

            if ($introDate > $now) {
                throw new BadRequest('Intro datum/tijd mag niet in de toekomst zijn.');
            }
        }

        if ($outcome === IntroMeetingOutcome::CANCELLED->value) {
            $action = $data->cancellationAction ?? null;

            if (!$action) {
                throw new BadRequest('Vervolgactie is verplicht bij annulering.');
            }

            if ($action === 'reschedule_now') {
                if (empty($data->calendarId) || empty($data->selectedDate) || empty($data->selectedTime)) {
                    throw new BadRequest('Nieuwe afspraakdatum en tijd zijn verplicht.');
                }
            }

            if ($action === 'reschedule_later' && empty($data->callAgainDateTime)) {
                throw new BadRequest('Datum voor terugbellen is verplicht.');
            }
        }

        if ($outcome === IntroMeetingOutcome::ATTENDED->value && !empty($data->nextBooking)) {
            $nextBooking = $data->nextBooking;
            $calendarId = $nextBooking->calendarId ?? null;
            $selectedDate = $nextBooking->selectedDate ?? null;
            $selectedTime = $nextBooking->selectedTime ?? null;

            if (empty($calendarId) || empty($selectedDate) || empty($selectedTime)) {
                throw new BadRequest('Volgende afspraak is niet volledig ingevuld.');
            }
        }

        if ($outcome !== IntroMeetingOutcome::ATTENDED->value && !empty($data->nextBooking)) {
            throw new BadRequest('Volgende afspraak is enkel mogelijk na een aanwezige intro.');
        }

        if (!empty($data->callAgainDateTime)) {
            $callAgain = new \DateTime($data->callAgainDateTime);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));

            if ($callAgain <= $now) {
                throw new BadRequest('Datum/tijd opnieuw bellen moet in de toekomst zijn.');
            }
        }
    }
}
