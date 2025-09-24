<?php

namespace Espo\Custom\Enums;

enum KickstartOutcome: string
{
    case CONVERTED = 'converted';
    case NO_SHOW = 'no_show';
    case NOT_CONVERTED = 'not_converted';
    case STILL_THINKING = 'still_thinking';

    public function getLabel(): string
    {
        return match($this) {
            self::CONVERTED => 'Converted',
            self::NO_SHOW => 'No Show',
            self::NOT_CONVERTED => 'Not Converted',
            self::STILL_THINKING => 'Still Thinking',
        };
    }

    public static function isValid(string $outcome): bool
    {
        return (bool) self::tryFrom($outcome);
    }
}
