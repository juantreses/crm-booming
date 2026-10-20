<?php

namespace Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;

use Espo\Modules\LeadManager\Handlers\KickstartFollowUp\BecameClientFollowUpHandler as KickstartBecameClientFollowUpHandler;

class BecameClientFollowUpHandler extends KickstartBecameClientFollowUpHandler
{
    protected const NOTE_SOURCE = 'RI - Opvolging';
}
