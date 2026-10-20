<?php

namespace Espo\Modules\LeadManager\Handlers\Royalty;

use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\Kickstart\StillThinkingHandler as KickstartStillThinkingHandler;

class StillThinkingHandler extends KickstartStillThinkingHandler
{
    protected const NOTE_SOURCE = 'Royalty';
    protected const FOLLOW_UP_NOTE = 'RI twijfel - Opvolging';

    public function getEventTypes(): array
    {
        return [LeadEventType::ATTENDED, LeadEventType::ROYALTY_STILL_THINKING];
    }
}
