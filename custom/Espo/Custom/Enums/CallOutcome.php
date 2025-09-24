<?php

namespace Espo\Custom\Enums;

enum CallOutcome: string
{
    case CALLED = 'called';
    case INVITED = 'invited';
    case CALL_AGAIN = 'call_again';
    case NO_ANSWER = 'no_answer';
    case WRONG_NUMBER = 'wrong_number';
    case NOT_INTERESTED = 'not_interested';

    public function getLabel(): string
    {
        return match ($this) {
            self::CALLED => 'Called',
            self::INVITED => 'Invited',
            self::CALL_AGAIN => 'Call Again',
            self::NO_ANSWER => 'No Answer',
            self::WRONG_NUMBER => 'Wrong Number',
            self::NOT_INTERESTED => 'Not Interested',
        };
    }

    public static function isValid(string $outcome): bool
    {
        return (bool) self::tryFrom($outcome);
    }
} 