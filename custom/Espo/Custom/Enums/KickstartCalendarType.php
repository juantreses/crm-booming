<?php

namespace Espo\Custom\Enums;

/**
 * Calendar types that follow the Kickstart flow.
 *
 * An online kickstart is booked, logged and followed up exactly like a
 * regular kickstart (same events, stages and buttons); only the calendar
 * (and therefore the meeting type on the lead) differs.
 */
enum KickstartCalendarType: string
{
    case KICKSTART = 'kickstart';
    case ONLINE = 'online';

    public static function isKickstart(?string $calendarType): bool
    {
        return $calendarType !== null && self::tryFrom(strtolower($calendarType)) !== null;
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::KICKSTART => 'Kickstart',
            self::ONLINE => 'Online Kickstart',
        };
    }
}
