<?php

namespace Espo\Custom\Enums;

enum IntroMeetingType: string
{
    case SPARK = 'spark';
    case BWS = 'bws';
    case HOM = 'hom';
    
    public function hasUsageLimit(): bool
    {
        return $this === self::SPARK;
    }
    
    public function getMaxUsage(): int
    {
        return match($this) {
            self::SPARK => 2,
            default => 1,
        };
    }
    
    public static function isIntroMeeting(string $calendarType): bool
    {
        return self::fromCalendarType($calendarType) !== null;
    }
    
    public static function fromCalendarType(string $calendarType): ?self
    {
        if ($calendarType === 'iom') {
            return self::HOM;
        }

        return self::tryFrom($calendarType);
    }
}
