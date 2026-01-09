<?php

namespace Espo\Modules\Booking\Tools\EmailTemplate\Placeholders;

use Espo\Modules\Crm\Entities\Meeting;
use Espo\Tools\EmailTemplate\Data;
use Espo\Tools\EmailTemplate\Placeholder;

class StartDate implements Placeholder
{
    public function get(Data $data): string
    {
        $entityHash = $data->getEntityHash();
        $meeting = $entityHash[Meeting::ENTITY_TYPE] ?? null;

        if (!$meeting || !$meeting->get('dateStart')) {
            return '';
        }

        try {
            $dateTime = new \DateTime($meeting->get('dateStart'));
            // Convert from UTC to Europe/Brussels timezone
            $dateTime->setTimezone(new \DateTimeZone('Europe/Brussels'));
            return $dateTime->format('d/m/Y');
        } catch (\Exception $e) {
            return '';
        }
    }
}

