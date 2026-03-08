<?php

namespace Espo\Custom\Enums;

enum IntroMeetingType: string
{
    case SPARK = 'spark';
    case BWS = 'bws';
    
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
        return self::tryFrom($calendarType) !== null;
    }
    
    public static function fromCalendarType(string $calendarType): ?self
    {
        return self::tryFrom($calendarType);
    }
}