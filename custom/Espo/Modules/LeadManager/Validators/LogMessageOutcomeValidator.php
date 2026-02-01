<?php

namespace Espo\Modules\LeadManager\Validators;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\MessageSentOutcome;

class LogMessageOutcomeValidator
{
    public function validate(\StdClass $data): void
    {
        if (!isset($data->id) || empty($data->id)) {
            throw new BadRequest('Lead ID is required.');
        }

        $outcome = $data->outcome ?? null;
        if (!$outcome || !MessageSentOutcome::isValid($outcome)) {
            throw new BadRequest('Invalid message outcome.');
        }
    
        if ($outcome === MessageSentOutcome::CALL_AGAIN->value) {
            if (empty($data->callAgainDateTime)) {
                throw new BadRequest('Datum/tijd opnieuw bellen is verplicht.');
            }
    
            $callAgain = new \DateTime($data->callAgainDateTime);
            $now = new \DateTime('now', new \DateTimeZone('UTC'));
    
            if ($callAgain <= $now) {
                throw new BadRequest('Datum/tijd opnieuw bellen moet in de toekomst zijn.');
            }
        }

        if ($outcome === MessageSentOutcome::INVITED->value) {
            $allowed = ['kickstart', 'bws', 'spark', 'iom'];
            $meetingType = $data->meetingType ?? '';
            if ($meetingType === '' || !in_array($meetingType, $allowed, true)) {
                throw new BadRequest('Type afspraak is verplicht en moet geldig zijn.');
            }
        }
    }
}