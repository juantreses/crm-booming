<?php

namespace Espo\Modules\LeadManager\Handlers\Royalty;

use Espo\Modules\LeadManager\Handlers\Kickstart\NotConvertedHandler as KickstartNotConvertedHandler;

class NotConvertedHandler extends KickstartNotConvertedHandler
{
    protected const NOTE_SOURCE = 'Royalty';
}
