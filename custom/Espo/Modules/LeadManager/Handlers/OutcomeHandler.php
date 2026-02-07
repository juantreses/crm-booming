<?php

namespace Espo\Modules\LeadManager\Handlers;

use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;

interface OutcomeHandler
{
    public function handle(string $leadId, array $context): OutcomeResult;
    
    public function getEventTypes(): array;
}