<?php

namespace Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;

use Espo\Modules\LeadManager\Handlers\KickstartFollowUp;

class NotConvertedFollowUpHandler extends KickstartFollowUp\NotConvertedFollowUpHandler
{
    protected const NOTE_SOURCE = 'RI - Opvolging';
}
