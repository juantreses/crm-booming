<?php

namespace Espo\Modules\LeadManager\Handlers\Royalty;

use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Handlers\Kickstart\BookedHandler as KickstartBookedHandler;

class BookedHandler extends KickstartBookedHandler
{
    protected const CALENDAR_TYPE = 'royalty';
    protected const PLANNED_STAGE = LeadStage::RI_PLANNED;

    public function getEventTypes(): array
    {
        return [LeadEventType::ROYALTY_BOOKED];
    }

    protected function isAllowedCalendarType(string $calendarType): bool
    {
        return $calendarType === static::CALENDAR_TYPE;
    }
}
