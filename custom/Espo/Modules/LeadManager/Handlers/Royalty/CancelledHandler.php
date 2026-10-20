<?php

namespace Espo\Modules\LeadManager\Handlers\Royalty;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\Kickstart\CancelledHandler as KickstartCancelledHandler;

class CancelledHandler extends KickstartCancelledHandler
{
    protected const NOTE_SOURCE = 'Royalty';
    protected const FOLLOW_UP_NOTE = 'RI geannuleerd - Opnieuw inplannen';
    protected const BOOKED_EVENT = LeadEventType::ROYALTY_BOOKED;

    public function getEventTypes(): array
    {
        return [LeadEventType::ROYALTY_CANCELLED];
    }
}
