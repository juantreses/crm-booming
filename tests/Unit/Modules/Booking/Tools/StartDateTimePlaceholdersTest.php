<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Tools;

use Espo\Modules\Booking\Tools\EmailTemplate\Placeholders\StartDate;
use Espo\Modules\Booking\Tools\EmailTemplate\Placeholders\StartTime;
use Espo\Tools\EmailTemplate\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;

final class StartDateTimePlaceholdersTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function dates(): iterable
    {
        yield 'winter' => ['2026-01-20 18:00:00', '20/01/2026', '19:00'];
        yield 'summer' => ['2026-07-01 17:30:00', '01/07/2026', '19:30'];
        yield 'next day in Brussels' => ['2026-07-01 22:30:00', '02/07/2026', '00:30'];
        yield 'no start' => [null, '', ''];
        yield 'invalid date' => ['geen datum', '', ''];
    }

    #[DataProvider('dates')]
    public function testShowsStartInBrusselsTime(?string $dateStart, string $date, string $time): void
    {
        $data = Data::create()->withEntityHash(['Meeting' => FakeEntity::create('Meeting', ['id' => 'm1', 'dateStart' => $dateStart])]);

        self::assertSame($date, (new StartDate())->get($data));
        self::assertSame($time, (new StartTime())->get($data));
    }

    public function testEmptyWithoutMeeting(): void
    {
        $data = Data::create()->withEntityHash(['Lead' => FakeEntity::create('Lead', ['id' => 'l1'])]);

        self::assertSame('', (new StartDate())->get($data));
        self::assertSame('', (new StartTime())->get($data));
    }
}
