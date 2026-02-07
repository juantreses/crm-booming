<?php

namespace Espo\Modules\Utils;

class DateTimeFactory
{
    private const TIMEZONE_UTC = 'UTC';
    private const TIMEZONE_BRUSSELS = 'Europe/Brussels';

    public static function nowUtc(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone(self::TIMEZONE_UTC));
    }

    public static function nowBrussels(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone(self::TIMEZONE_BRUSSELS));
    }

    public static function parseToUtc(?string $dateTime): \DateTime
    {
        if (!$dateTime) {
            return self::nowUtc();
        }

        $dt = new \DateTime($dateTime);
        $dt->setTimezone(new \DateTimeZone(self::TIMEZONE_UTC));
        return $dt;
    }

    public static function parseToBrussels(?string $dateTime): \DateTime
    {
        if (!$dateTime) {
            return self::nowBrussels();
        }

        $dt = new \DateTime($dateTime);
        $dt->setTimezone(new \DateTimeZone(self::TIMEZONE_BRUSSELS));
        return $dt;
    }

    public static function formatUtc(\DateTime $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    public static function formatBrussels(\DateTime $dateTime): string
    {
        return $dateTime->format('d/m/Y H:i');
    }

    public static function formatBrusselsWithBrackers(\DateTime $dateTime): string
    {
        return $dateTime->format('[d/m/Y H:i]');
    }
}