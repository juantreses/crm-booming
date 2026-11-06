<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Links\Services;

use Espo\Core\Utils\Config;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Links\Services\CalendarLinkBuilder;
use Espo\Modules\Links\ValueObjects\CalendarLink;
use Espo\ORM\EntityCollection;
use PHPUnit\Framework\TestCase;
use Tests\Support\EntityStubs;

final class CalendarLinkBuilderTest extends TestCase
{
    use EntityStubs;

    private const BASE = 'https://crm.test/?entryPoint=widget&type=calendar';

    public function testSingleLocationWithoutVariantsGivesOneLink(): void
    {
        $links = $this->buildForCenter(
            [['id' => 'cal1', 'name' => 'Kickstart', 'slug' => 'kickstart']],
            ['cal1' => [$this->location('loc1', 'Gent', 'gent')]],
        );

        self::assertEquals([new CalendarLink('Kickstart', self::BASE . '&id=kickstart')], $links);
    }

    public function testCalendarWithoutSlugUsesId(): void
    {
        $links = $this->buildForCenter(
            [['id' => 'cal1', 'name' => 'Kickstart', 'slug' => null]],
            ['cal1' => []],
        );

        self::assertEquals([new CalendarLink('Kickstart', self::BASE . '&id=cal1')], $links);
    }

    public function testMultipleLocationsGiveOverviewAndOneLinkPerLocation(): void
    {
        $links = $this->buildForCenter(
            [['id' => 'cal1', 'name' => 'Kickstart', 'slug' => 'kickstart']],
            ['cal1' => [$this->location('loc1', 'Gent', 'gent'), $this->location('loc2', 'Brugge', 'brugge')]],
        );

        self::assertEquals([
            new CalendarLink('Kickstart', self::BASE . '&id=kickstart', subtext: 'Alle locaties'),
            new CalendarLink('Kickstart - Gent', self::BASE . '&id=kickstart&location=gent', isLocation: true),
            new CalendarLink('Kickstart - Brugge', self::BASE . '&id=kickstart&location=brugge', isLocation: true),
        ], $links);
    }

    public function testSingleLocationWithVariantsGivesSessionOverviewAndVariantLinks(): void
    {
        $links = $this->buildForCenter(
            [['id' => 'cal1', 'name' => 'Kickstart', 'slug' => 'kickstart']],
            ['cal1' => [$this->location('loc1', 'Gent', 'gent', [['slug' => 'avond', 'label' => 'Avondsessie']])]],
        );

        self::assertEquals([
            new CalendarLink('Kickstart', self::BASE . '&id=kickstart', subtext: 'Alle sessies'),
            new CalendarLink('Kickstart - Gent', self::BASE . '&id=kickstart&location=gent', isLocation: true),
            new CalendarLink('Avondsessie', self::BASE . '&id=kickstart&location=gent&variant=avond', isVariant: true),
        ], $links);
    }

    public function testVariantsWithoutLocationHaveNoLocationParameter(): void
    {
        $links = $this->buildForCenter(
            [['id' => 'cal1', 'name' => 'Online', 'slug' => 'online']],
            ['cal1' => [$this->location(null, null, null, [['slug' => 'zoom', 'label' => 'Zoom']])]],
        );

        self::assertEquals([
            new CalendarLink('Online', self::BASE . '&id=online', subtext: 'Alle sessies'),
            new CalendarLink('Zoom', self::BASE . '&id=online&variant=zoom', isVariant: true),
        ], $links);
    }

    public function testBuildForTeamAddsCoachAndPassesTeamToRepository(): void
    {
        $repository = $this->createMock(CalendarRepository::class);
        $repository->expects(self::once())
            ->method('getCalendarsForTeam')
            ->with('team1')
            ->willReturn(new EntityCollection([$this->entityStub(['id' => 'cal1', 'name' => 'Kickstart', 'slug' => 'kickstart'])]));
        $repository->expects(self::once())
            ->method('getPublicLinkStructureForCalendar')
            ->with('cal1', 'team1')
            ->willReturn([]);

        $links = (new CalendarLinkBuilder($this->config(), $repository))->buildForTeam('team1', 'jan');

        self::assertEquals([new CalendarLink('Kickstart', self::BASE . '&id=kickstart&coach=jan')], $links);
    }

    public function testBuildForCenterPassesNoTeam(): void
    {
        $repository = $this->createMock(CalendarRepository::class);
        $repository->method('getActiveCalendars')
            ->willReturn(new EntityCollection([$this->entityStub(['id' => 'cal1', 'name' => 'Kickstart'])]));
        $repository->expects(self::once())
            ->method('getPublicLinkStructureForCalendar')
            ->with('cal1', null)
            ->willReturn([]);

        (new CalendarLinkBuilder($this->config(), $repository))->buildForCenter();
    }

    /**
     * @param list<array<string, mixed>> $calendars
     * @param array<string, list<array<string, mixed>>> $structureByCalendar
     * @return CalendarLink[]
     */
    private function buildForCenter(array $calendars, array $structureByCalendar): array
    {
        $repository = $this->createStub(CalendarRepository::class);
        $repository->method('getActiveCalendars')
            ->willReturn(new EntityCollection(array_map(fn (array $c) => $this->entityStub($c), $calendars)));
        $repository->method('getPublicLinkStructureForCalendar')
            ->willReturnCallback(static fn (string $calendarId) => $structureByCalendar[$calendarId]);

        return (new CalendarLinkBuilder($this->config(), $repository))->buildForCenter();
    }

    /**
     * Same shape as CalendarRepository::getPublicLinkStructureForCalendar() rows.
     *
     * @param list<array{slug: string, label: string}> $variants
     * @return array<string, mixed>
     */
    private function location(?string $id, ?string $name, ?string $slug, array $variants = []): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'variants' => array_map(static fn (array $v) => $v + ['description' => null], $variants),
        ];
    }

    private function config(): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('get')->willReturnMap([['siteUrl', null, 'https://crm.test/']]);

        return $config;
    }
}
