<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Calendar\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Log;
use Espo\Modules\Calendar\Controllers\CalendarApi;
use Espo\Modules\Calendar\Services\CalendarService;
use Espo\Modules\Calendar\Services\PublicCalendarAccess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeEntity;
use Throwable;

final class CalendarApiTest extends TestCase
{
    /** @var list<array<int, mixed>> service method + arguments */
    private array $calls = [];

    /** @var list<string> */
    private array $logged = [];

    /** @var list<array{string, bool}> identifier + allowAuthenticatedCrmUser */
    private array $accessChecks = [];

    private ?Throwable $failure = null;

    public function testSettings(): void
    {
        $result = $this->api()->getActionSettings($this->request(['id' => 'kickstart'], ['location' => 'gent', 'coach' => 'jan', 'variant' => 'avond']));

        self::assertSame([['kickstart', false]], $this->accessChecks);
        // The service gets the resolved calendar id, not the slug.
        self::assertSame([['getSettings', 'cal1', 'gent', 'jan', 'avond']], $this->calls);
        self::assertSame(['result' => 'getSettings'], $result);
    }

    public function testSlotsDefaultToToday(): void
    {
        $this->api()->getActionSlots($this->request(['id' => 'kickstart']));
        $this->api()->getActionSlots($this->request(['id' => 'kickstart'], ['date' => '2026-01-20', 'location' => 'gent']));

        self::assertSame([
            ['getAvailableSlots', 'cal1', date('Y-m-d'), null, null, null],
            ['getAvailableSlots', 'cal1', '2026-01-20', 'gent', null, null],
        ], $this->calls);
    }

    public function testMonthAvailabilityDefaultsToThisMonth(): void
    {
        $this->api()->getActionAvailability($this->request(['id' => 'kickstart']));
        $this->api()->getActionAvailability($this->request(['id' => 'kickstart'], ['year' => '2026', 'month' => '03', 'variant' => 'avond']));

        self::assertSame([
            ['getMonthAvailability', 'cal1', (int) date('Y'), (int) date('m'), null, null, null],
            ['getMonthAvailability', 'cal1', 2026, 3, null, null, 'avond'],
        ], $this->calls);
    }

    public function testInternalEndpointsSkipThePublicAccessCheck(): void
    {
        $this->api()->getActionByType($this->request(['type' => 'workout']));
        $this->api()->getActionBookableList();
        $this->api()->getActionUpcomingSlots($this->request([], ['id' => 'kickstart', 'coach' => 'jan']));

        self::assertSame([
            ['getCalendarByType', 'workout'],
            ['getBookableCalendars'],
            ['getUpcomingSlots', 'kickstart', 'jan'],
        ], $this->calls);
        self::assertSame([], $this->accessChecks);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, array<string, string>, string}>
     */
    public static function missingParameters(): iterable
    {
        yield 'settings' => ['getActionSettings', [], [], 'Missing required parameter: id'];
        yield 'slots' => ['getActionSlots', [], [], 'Missing required parameter: id'];
        yield 'availability' => ['getActionAvailability', [], [], 'Missing required parameter: id'];
        yield 'by type' => ['getActionByType', [], [], 'Missing required parameter: type'];
        // upcoming-slots reads the id from the query string, not the route.
        yield 'upcoming slots' => ['getActionUpcomingSlots', ['id' => 'kickstart'], [], 'Missing required parameter: id'];
    }

    /**
     * @param array<string, string> $route
     * @param array<string, string> $query
     */
    #[DataProvider('missingParameters')]
    public function testMissingParameterIs400(string $action, array $route, array $query, string $message): void
    {
        $result = $this->api()->$action($this->request($route, $query));

        self::assertSame(['success' => false, 'error' => $message, 'code' => 400], $result);
        self::assertSame([], $this->calls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function actionsWithId(): iterable
    {
        yield 'settings' => ['getActionSettings'];
        yield 'slots' => ['getActionSlots'];
        yield 'availability' => ['getActionAvailability'];
    }

    #[DataProvider('actionsWithId')]
    public function testInaccessibleCalendarIs404(string $action): void
    {
        $this->failure = new NotFound('Deze boekingslink is niet meer beschikbaar.');

        $result = $this->api()->$action($this->request(['id' => 'geheim']));

        self::assertSame(['success' => false, 'error' => 'Deze boekingslink is niet meer beschikbaar.', 'code' => 404], $result);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, array<string, string>, string}>
     */
    public static function serviceErrors(): iterable
    {
        yield 'settings' => ['getActionSettings', ['id' => 'kickstart'], [], 'getSettings'];
        yield 'slots' => ['getActionSlots', ['id' => 'kickstart'], [], 'getSlots'];
        yield 'availability' => ['getActionAvailability', ['id' => 'kickstart'], [], 'getAvailability'];
        yield 'by type' => ['getActionByType', ['type' => 'workout'], [], 'getByType'];
        yield 'upcoming slots' => ['getActionUpcomingSlots', [], ['id' => 'kickstart'], 'getUpcomingSlots'];
    }

    /**
     * @param array<string, string> $route
     * @param array<string, string> $query
     */
    #[DataProvider('serviceErrors')]
    public function testServiceErrors(string $action, array $route, array $query, string $logName): void
    {
        $api = $this->api(serviceFailure: new BadRequest('Calendar identifier not found: x'));
        self::assertSame(['success' => false, 'error' => 'Calendar identifier not found: x', 'code' => 400], $api->$action($this->request($route, $query)));

        $api = $this->api(serviceFailure: new NotFound('Calendar not active'));
        self::assertSame(['success' => false, 'error' => 'Calendar not active', 'code' => 404], $api->$action($this->request($route, $query)));

        // Other errors are logged and hidden from the visitor.
        $api = $this->api(serviceFailure: new RuntimeException('SQL error'));
        self::assertSame(['success' => false, 'error' => 'Internal server error', 'code' => 500], $api->$action($this->request($route, $query)));
        self::assertSame(["Calendar API Error ($logName): SQL error"], $this->logged);
    }

    public function testBookableListError(): void
    {
        $result = $this->api(serviceFailure: new RuntimeException('SQL error'))->getActionBookableList();

        self::assertSame(['success' => false, 'error' => 'Internal server error', 'code' => 500], $result);
        self::assertSame(['Calendar API Error (getBookableList): SQL error'], $this->logged);
    }

    /**
     * @param array<string, string> $route
     * @param array<string, string> $query
     */
    private function request(array $route, array $query = []): Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getRouteParam')->willReturnCallback(static fn (string $name) => $route[$name] ?? null);
        $request->method('getQueryParam')->willReturnCallback(static fn (string $name) => $query[$name] ?? null);

        return $request;
    }

    private function api(?Throwable $serviceFailure = null): CalendarApi
    {
        $service = $this->createStub(CalendarService::class);
        foreach (['getSettings', 'getAvailableSlots', 'getMonthAvailability', 'getCalendarByType', 'getBookableCalendars', 'getUpcomingSlots'] as $method) {
            $service->method($method)->willReturnCallback(function (mixed ...$arguments) use ($method, $serviceFailure): array {
                if ($serviceFailure) {
                    throw $serviceFailure;
                }

                $this->calls[] = [$method, ...$arguments];

                return ['result' => $method];
            });
        }

        $access = $this->createStub(PublicCalendarAccess::class);
        $access->method('resolveAccessible')->willReturnCallback(function (string $identifier, bool $allowCrmUser = false) {
            if ($this->failure) {
                throw $this->failure;
            }

            $this->accessChecks[] = [$identifier, $allowCrmUser];

            return FakeEntity::create('CCalendar', ['id' => 'cal1']);
        });

        $log = $this->createStub(Log::class);
        $log->method('error')->willReturnCallback(function (string $message): void {
            $this->logged[] = $message;
        });

        return new CalendarApi($log, $service, $access);
    }
}
