<?php

declare(strict_types=1);

namespace Tests\Unit\Custom\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Authentication\AuthToken\AuthToken;
use Espo\Core\Authentication\AuthToken\Manager as AuthTokenManager;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\EntryPoints\Widget;
use Espo\Entities\User;
use Espo\Modules\Calendar\Services\PublicCalendarAccess;
use Espo\ORM\Repository\RDBRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class WidgetTest extends TestCase
{
    use InMemoryEntityManager;

    private const LAYOUTS = 'custom/Espo/Custom/Resources/layouts/EntryPoints/';

    private ?string $body = null;

    private ?int $status = null;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{string, bool}> calendar identifier + allowAuthenticatedCrmUser */
    private array $accessChecks = [];

    private bool $calendarAccessible = true;

    /** @var array<string, AuthToken> */
    private array $tokens = [];

    /** @var array<string, User> */
    private array $users = [];

    /**
     * @return iterable<string, array{string}>
     */
    public static function plainWidgets(): iterable
    {
        foreach (['survey', 'referral', 'direct', 'voucher'] as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('plainWidgets')]
    public function testServesTheLayoutOfTheWidget(string $type): void
    {
        $this->open(['type' => $type]);

        self::assertSame(200, $this->status);
        self::assertSame((string) file_get_contents(self::LAYOUTS . "$type.html"), $this->body);
        self::assertSame(['Content-Type' => 'text/html'], $this->headers);
        self::assertSame([], $this->accessChecks);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidTypes(): iterable
    {
        yield 'none' => [null];
        yield 'unknown' => ['not-found'];
        yield 'path' => ['../../../../data/config'];
    }

    #[DataProvider('invalidTypes')]
    public function testRejectsOtherTypes(?string $type): void
    {
        $this->open(['type' => $type]);

        self::assertSame(400, $this->status);
        self::assertSame('Fout: Ongeldig widget type.', $this->body);
    }

    public function testCalendarForAnonymousVisitor(): void
    {
        $this->open(['type' => 'calendar', 'id' => 'kickstart']);

        self::assertSame(200, $this->status);
        self::assertSame((string) file_get_contents(self::LAYOUTS . 'calendar.html'), $this->body);
        self::assertSame([['kickstart', false]], $this->accessChecks);
    }

    public function testCalendarWithoutIdOrNotAccessibleShowsNotFound(): void
    {
        $notFound = (string) file_get_contents(self::LAYOUTS . 'not-found.html');

        $this->open(['type' => 'calendar']);
        self::assertSame([404, $notFound], [$this->status, $this->body]);
        self::assertSame([], $this->accessChecks);

        $this->calendarAccessible = false;
        $this->open(['type' => 'calendar', 'id' => 'geheim']);
        self::assertSame([404, $notFound], [$this->status, $this->body]);
        self::assertSame(['Content-Type' => 'text/html'], $this->headers);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, bool>, ?string, bool}>
     */
    public static function crmUsers(): iterable
    {
        $regular = ['isRegular' => true];

        // token settings, user flags, secret cookie => allowed to see non-public calendars
        yield 'regular user' => [[], $regular, null, true];
        yield 'admin' => [[], ['isAdmin' => true], null, true];
        yield 'with matching secret' => [['secret' => 's3cret'], $regular, 's3cret', true];
        yield 'with wrong secret' => [['secret' => 's3cret'], $regular, 'fout', false];
        yield 'secret cookie missing' => [['secret' => 's3cret'], $regular, null, false];
        yield 'inactive token' => [['active' => false], $regular, null, false];
        yield 'portal token' => [['portalId' => 'portal1'], $regular, null, false];
        yield 'unknown user' => [['userId' => 'weg'], $regular, null, false];
        yield 'inactive user' => [[], ['isRegular' => true, 'isActive' => false], null, false];
        yield 'system user' => [[], ['isAdmin' => true, 'isSystem' => true], null, false];
        yield 'portal user' => [[], ['isRegular' => true, 'isPortal' => true], null, false];
        yield 'api user' => [[], ['isRegular' => true, 'isApi' => true], null, false];
        yield 'other user type' => [[], [], null, false];
    }

    /**
     * @param array<string, mixed> $tokenSettings
     * @param array<string, bool> $userFlags
     */
    #[DataProvider('crmUsers')]
    public function testLoggedInCrmUserMaySeeNonPublicCalendars(array $tokenSettings, array $userFlags, ?string $secretCookie, bool $allowed): void
    {
        $this->users['user1'] = $this->user($userFlags);
        $this->tokens['tok'] = $this->token($tokenSettings + ['userId' => 'user1', 'active' => true, 'portalId' => null, 'secret' => null]);

        $this->open(['type' => 'calendar', 'id' => 'kickstart'], ['auth-token' => 'tok', 'auth-token-secret' => $secretCookie]);

        self::assertSame([['kickstart', $allowed]], $this->accessChecks);
    }

    public function testUnknownTokenIsAnonymous(): void
    {
        $this->open(['type' => 'calendar', 'id' => 'kickstart'], ['auth-token' => 'onbekend']);

        self::assertSame([['kickstart', false]], $this->accessChecks);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function token(array $settings): AuthToken
    {
        $token = $this->createStub(AuthToken::class);
        $token->method('getUserId')->willReturn($settings['userId']);
        $token->method('isActive')->willReturn($settings['active']);
        $token->method('getPortalId')->willReturn($settings['portalId']);
        $token->method('getSecret')->willReturn($settings['secret']);

        return $token;
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

    /**
     * @param array<string, ?string> $query
     * @param array<string, ?string> $cookies
     */
    private function open(array $query, array $cookies = []): void
    {
        $this->body = null;
        $this->status = null;
        $this->headers = [];

        $request = $this->createStub(Request::class);
        $request->method('getQueryParam')->willReturnCallback(static fn (string $name) => $query[$name] ?? null);
        $request->method('getCookieParam')->willReturnCallback(static fn (string $name) => $cookies[$name] ?? null);

        $response = $this->createStub(Response::class);
        $response->method('writeBody')->willReturnCallback(function (string $body) use ($response): Response {
            $this->body = $body;

            return $response;
        });
        $response->method('setStatus')->willReturnCallback(function (int $status) use ($response): Response {
            $this->status = $status;

            return $response;
        });
        $response->method('setHeader')->willReturnCallback(function (string $name, string $value) use ($response): Response {
            $this->headers[$name] = $value;

            return $response;
        });

        $access = $this->createStub(PublicCalendarAccess::class);
        $access->method('resolveAccessible')->willReturnCallback(function (string $identifier, bool $allowCrmUser = false) {
            $this->accessChecks[] = [$identifier, $allowCrmUser];

            if (!$this->calendarAccessible) {
                throw new NotFound('Deze boekingslink is niet meer beschikbaar.');
            }

            return FakeEntity::create('CCalendar', ['id' => 'cal1']);
        });

        $tokenManager = $this->createStub(AuthTokenManager::class);
        $tokenManager->method('get')->willReturnCallback(fn (string $token) => $this->tokens[$token] ?? null);

        $userRepository = $this->createStub(RDBRepository::class);
        $userRepository->method('getById')->willReturnCallback(fn (string $id) => $this->users[$id] ?? null);
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepositoryByClass')->willReturn($userRepository);

        (new Widget($access, $tokenManager, $entityManager))->run($request, $response);
    }
}
