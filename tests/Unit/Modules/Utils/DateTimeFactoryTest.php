<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Utils;

use DateTime;
use DateTimeZone;
use Espo\Modules\Utils\DateTimeFactory;
use PHPUnit\Framework\TestCase;

final class DateTimeFactoryTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        // A date string without an offset is parsed in PHP's default timezone. Espo runs with UTC.
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    public function testNowUtcIsInUtc(): void
    {
        $now = DateTimeFactory::nowUtc();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertEqualsWithDelta(time(), $now->getTimestamp(), 5);
    }

    public function testNowBrusselsIsInBrussels(): void
    {
        $now = DateTimeFactory::nowBrussels();

        self::assertSame('Europe/Brussels', $now->getTimezone()->getName());
        self::assertEqualsWithDelta(time(), $now->getTimestamp(), 5);
    }

    public function testParseToUtcConvertsOffsetToUtc(): void
    {
        $dt = DateTimeFactory::parseToUtc('2026-03-29 12:00:00+02:00');

        self::assertSame('UTC', $dt->getTimezone()->getName());
        self::assertSame('2026-03-29 10:00:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testParseToUtcTreatsStringWithoutOffsetAsDefaultTimezone(): void
    {
        $dt = DateTimeFactory::parseToUtc('2026-03-29 12:00:00');

        self::assertSame('2026-03-29 12:00:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testParseToUtcFallsBackToNowForEmptyInput(): void
    {
        foreach ([null, ''] as $input) {
            $dt = DateTimeFactory::parseToUtc($input);

            self::assertSame('UTC', $dt->getTimezone()->getName());
            self::assertEqualsWithDelta(time(), $dt->getTimestamp(), 5);
        }
    }

    public function testParseToBrusselsAppliesSummerTime(): void
    {
        $dt = DateTimeFactory::parseToBrussels('2026-07-01 10:00:00');

        self::assertSame('Europe/Brussels', $dt->getTimezone()->getName());
        self::assertSame('2026-07-01 12:00:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testParseToBrusselsAppliesWinterTime(): void
    {
        $dt = DateTimeFactory::parseToBrussels('2026-01-15 10:00:00');

        self::assertSame('2026-01-15 11:00:00', $dt->format('Y-m-d H:i:s'));
    }

    public function testParseToBrusselsFallsBackToNowForEmptyInput(): void
    {
        $dt = DateTimeFactory::parseToBrussels(null);

        self::assertSame('Europe/Brussels', $dt->getTimezone()->getName());
        self::assertEqualsWithDelta(time(), $dt->getTimestamp(), 5);
    }

    public function testFormatters(): void
    {
        $dt = new DateTime('2026-01-05 08:07:09', new DateTimeZone('UTC'));

        self::assertSame('2026-01-05 08:07:09', DateTimeFactory::formatUtc($dt));
        self::assertSame('05/01/2026 08:07', DateTimeFactory::formatBrussels($dt));
        self::assertSame('[05/01/2026 08:07]', DateTimeFactory::formatBrusselsWithBrackers($dt));
    }

    public function testFormatBrusselsDoesNotConvertTimezone(): void
    {
        // The format* methods only format: callers must convert to Brussels first (parseToBrussels).
        $utc = new DateTime('2026-07-01 10:00:00', new DateTimeZone('UTC'));

        self::assertSame('01/07/2026 10:00', DateTimeFactory::formatBrussels($utc));
    }
}
