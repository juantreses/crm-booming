<?php

namespace Espo\Custom\Enums;

/**
 * Score a lead gives itself on paper during a HOM.
 * A/B continue towards SPARK/Kickstart, C/D go straight to a Royalty (RI) meeting.
 */
enum HomRating: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    public function leadsToRoyalty(): bool
    {
        return $this === self::C || $this === self::D;
    }

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
