<?php

namespace Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;

use Espo\Modules\LeadManager\Handlers\KickstartFollowUp\NotConvertedFollowUpHandler as KickstartNotConvertedFollowUpHandler;

class NotConvertedFollowUpHandler extends KickstartNotConvertedFollowUpHandler
{
    protected const NOTE_SOURCE = 'RI - Opvolging';
}
