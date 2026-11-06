<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Links\Services;

use Espo\Core\Utils\Config;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Links\Services\CalendarLinkBuilder;
use Espo\Modules\Links\Services\LinkGeneratorService;
use Espo\Modules\Links\Services\WidgetLinkBuilder;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Tests\Support\EntityStubs;

final class LinkGeneratorServiceTest extends TestCase
{
    use EntityStubs;

    public function testUnknownTeamGivesEmptyCollection(): void
    {
        $service = $this->service(['CTeam' => []]);

        $links = $service->getLinksForTeam('missing');

        self::assertFalse($links->hasLinks());
        self::assertSame(['widgets' => [], 'calendars' => []], $links->toArray());
    }

    public function testCoachIsTheSlugOfTheAssignedUser(): void
    {
        $service = $this->service([
            'CTeam' => ['team1' => $this->entityStub(['id' => 'team1', 'assignedUserId' => 'user1'])],
            'User' => ['user1' => $this->entityStub(['id' => 'user1', 'cSlug' => 'jan'])],
        ]);

        $links = $service->getLinksForTeam('team1');

        self::assertSame('https://crm.test/?entryPoint=widget&type=survey&coach=jan', $links->widgets[0]->url);
        self::assertSame('https://crm.test/?entryPoint=widget&type=calendar&id=kickstart&coach=jan', $links->calendars[0]->url);
    }

    public function testCoachFallsBackToTeamIdWhenUserHasNoSlug(): void
    {
        $service = $this->service([
            'CTeam' => ['team1' => $this->entityStub(['id' => 'team1', 'assignedUserId' => 'user1'])],
            'User' => ['user1' => $this->entityStub(['id' => 'user1', 'cSlug' => null])],
        ]);

        self::assertStringEndsWith('&coach=team1', $service->getLinksForTeam('team1')->widgets[0]->url);
    }

    public function testCoachFallsBackToTeamIdWhenNoUserAssigned(): void
    {
        $service = $this->service([
            'CTeam' => ['team1' => $this->entityStub(['id' => 'team1', 'assignedUserId' => null])],
        ]);

        self::assertStringEndsWith('&coach=team1', $service->getLinksForTeam('team1')->widgets[0]->url);
    }

    public function testCoachFallsBackToTeamIdWhenAssignedUserIsMissing(): void
    {
        $service = $this->service([
            'CTeam' => ['team1' => $this->entityStub(['id' => 'team1', 'assignedUserId' => 'deleted-user'])],
            'User' => [],
        ]);

        self::assertStringEndsWith('&coach=team1', $service->getLinksForTeam('team1')->widgets[0]->url);
    }

    public function testCenterLinksHaveNoCoach(): void
    {
        $links = $this->service([])->getLinksForCenter();

        self::assertCount(4, $links->widgets);
        self::assertSame('https://crm.test/?entryPoint=widget&type=survey', $links->widgets[0]->url);
        self::assertSame('https://crm.test/?entryPoint=widget&type=calendar&id=kickstart', $links->calendars[0]->url);
    }

    /**
     * @param array<string, array<string, Entity>> $entitiesByType
     */
    private function service(array $entitiesByType): LinkGeneratorService
    {
        $entityManager = $this->createStub(EntityManager::class);
        $entityManager->method('getEntityById')->willReturnCallback(
            static fn (string $type, string $id) => $entitiesByType[$type][$id] ?? null,
        );

        $config = $this->createStub(Config::class);
        $config->method('get')->willReturnMap([['siteUrl', null, 'https://crm.test']]);

        $calendars = fn () => new EntityCollection([$this->entityStub(['id' => 'cal1', 'name' => 'Kickstart', 'slug' => 'kickstart'])]);
        $repository = $this->createStub(CalendarRepository::class);
        $repository->method('getCalendarsForTeam')->willReturnCallback($calendars);
        $repository->method('getActiveCalendars')->willReturnCallback($calendars);
        $repository->method('getPublicLinkStructureForCalendar')->willReturn([]);

        return new LinkGeneratorService(
            $entityManager,
            new WidgetLinkBuilder($config),
            new CalendarLinkBuilder($config, $repository),
        );
    }
}
