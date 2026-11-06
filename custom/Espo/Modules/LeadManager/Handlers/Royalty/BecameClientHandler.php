<?php

namespace Espo\Modules\LeadManager\Handlers\Royalty;

use Espo\Modules\LeadManager\Handlers\Kickstart\BecameClientHandler as KickstartBecameClientHandler;

class BecameClientHandler extends KickstartBecameClientHandler
{
    protected const NOTE_SOURCE = 'Royalty';
}
