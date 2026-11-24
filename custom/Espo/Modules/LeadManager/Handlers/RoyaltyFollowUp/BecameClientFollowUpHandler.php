<?php

namespace Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;

use Espo\Modules\LeadManager\Handlers\KickstartFollowUp;

class BecameClientFollowUpHandler extends KickstartFollowUp\BecameClientFollowUpHandler
{
    protected const NOTE_SOURCE = 'RI - Opvolging';
}
