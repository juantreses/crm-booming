<?php

namespace Espo\Custom\Enums;

enum IntroMeetingOutcome: string
{
    case ATTENDED = 'attended';              // Attended the intro meeting
    case NO_SHOW = 'no_show';                // Didn't show up
    case CANCELLED = 'cancelled';            // Meeting was cancelled
    
    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
    
    public function getLabel(): string
    {
        return match($this) {
            self::ATTENDED => 'Aanwezig',
            self::NO_SHOW => 'Niet opgedaagd',
            self::CANCELLED => 'Geannuleerd',
        };
    }
}