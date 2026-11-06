<?php

namespace Espo\Modules\LeadManager\Handlers\Royalty;

use Espo\Modules\LeadManager\Handlers\Kickstart\NoShowHandler as KickstartNoShowHandler;

class NoShowHandler extends KickstartNoShowHandler
{
    protected const NOTE_SOURCE = 'Royalty';
    protected const FOLLOW_UP_NOTE = 'Niet opgedaagd RI';
}
