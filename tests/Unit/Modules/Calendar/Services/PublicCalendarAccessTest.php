<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Calendar\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Calendar\Services\PublicCalendarAccess;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;

final class PublicCalendarAccessTest extends TestCase
{
    private const UNAVAILABLE = 'Deze boekingslink is niet meer beschikbaar.';

    public function testPublicCalendarIsOpenToEveryone(): void
    {
        $calendar = $this->calendar(isPublic: true);

        self::assertSame($calendar, $this->access($calendar, $this->anonymous())->resolveAccessible('kickstart'));
    }

    public function testNonPublicCalendarForAnonymousVisitor(): void
    {
        $this->expectException(NotFound::class);
        $this->expectExceptionMessage(self::UNAVAILABLE);

        $this->access($this->calendar(isPublic: false), $this->anonymous())->resolveAccessible('kickstart');
    }

    public function testNonPublicCalendarWhenTheCallerAllowsCrmUsers(): void
    {
        $calendar = $this->calendar(isPublic: false);

        self::assertSame($calendar, $this->access($calendar, $this->anonymous())->resolveAccessible('kickstart', true));
    }

    /**
     * @return iterable<string, array{array<string, bool>, bool}>
     */
    public static function users(): iterable
    {
        yield 'regular user' => [['isRegular' => true], true];
        yield 'admin' => [['isAdmin' => true], true];
        yield 'inactive regular user' => [['isRegular' => true, 'isActive' => false], false];
        yield 'system user' => [['isAdmin' => true, 'isSystem' => true], false];
        yield 'portal user' => [['isRegular' => true, 'isPortal' => true], false];
        yield 'api user' => [['isRegular' => true, 'isApi' => true], false];
    }

    /**
     * @param array<string, bool> $flags
     */
    #[DataProvider('users')]
    public function testNonPublicCalendarForLoggedInUser(array $flags, bool $allowed): void
    {
        $calendar = $this->calendar(isPublic: false);

        if (!$allowed) {
            $this->expectException(NotFound::class);
        }

        self::assertSame($calendar, $this->access($calendar, $this->user($flags))->resolveAccessible('kickstart'));
    }

    public function testUnknownInactiveOrMissingCalendar(): void
    {
        foreach (
            [
            ['onbekend', $this->calendar(isPublic: true)],
            ['kickstart', $this->calendar(isPublic: true, isActive: false)],
            ['kickstart', null],
            ] as [$identifier, $calendar]
        ) {
            try {
                $this->access($calendar, $this->user(['isAdmin' => true]))->resolveAccessible($identifier, true);
                self::fail("Expected NotFound for $identifier");
            } catch (NotFound $e) {
                self::assertSame(self::UNAVAILABLE, $e->getMessage());
            }
        }
    }

    private function calendar(bool $isPublic, bool $isActive = true): Entity
    {
        return FakeEntity::create('CCalendar', ['id' => 'cal1', 'isActive' => $isActive, 'isPubliek' => $isPublic]);
    }

    private function anonymous(): User
    {
        return $this->user([]);
    }

    /**
     * @param array<string, bool> $flags
     */
    private function user(array $flags): User
    {
        $flags += ['isActive' => true, 'isSystem' => false, 'isPortal' => false, 'isApi' => false, 'isRegular' => false, 'isAdmin' => false];
        $user = $this->createStub(User::class);
        foreach ($flags as $method => $value) {
            $user->method($method)->willReturn($value);
        }

        return $user;
    }

    private function access(?Entity $calendar, User $user): PublicCalendarAccess
    {
        $slugService = $this->createStub(SlugService::class);
        $slugService->method('resolve')->willReturnCallback(static fn (string $type, ?string $id) => $id === 'kickstart' ? 'cal1' : null);

        $repository = $this->createStub(CalendarRepository::class);
        $repository->method('findCalendarById')->willReturn($calendar);

        return new PublicCalendarAccess($slugService, $repository, $user);
    }
}
