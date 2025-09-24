<?php

namespace Espo\Custom\Enums;

enum MessageSentOutcome: string
{
    case CALL_AGAIN = 'call_again';
    case NOT_INTERESTED = 'not_interested';

    public function getLabel(): string
    {
        return match ($this) {
            self::CALL_AGAIN => 'Call Again',
            self::NOT_INTERESTED => 'Not Interested',
        };
    }

    public static function isValid(string $outcome): bool
    {
        return (bool) self::tryFrom($outcome);
    }
} 