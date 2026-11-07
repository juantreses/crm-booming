<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ContactManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Log;
use Espo\Modules\ContactManager\Controllers\ContactHomApi;
use Espo\Modules\ContactManager\Services\ContactHomService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ContactHomApiTest extends TestCase
{
    private mixed $originalLog = null;

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        $this->originalLog = $GLOBALS['log'] ?? null;
        $log = $this->createStub(Log::class);
        $log->method('error')->willReturnCallback(function (string $message): void {
            $this->logged[] = $message;
        });
        $GLOBALS['log'] = $log;
    }

    protected function tearDown(): void
    {
        $GLOBALS['log'] = $this->originalLog;
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function actions(): iterable
    {
        yield 'book' => ['postActionBook', 'book', 'HOM boeken mislukt', 'Contact HOM Book Error'];
        yield 'log' => ['postActionLog', 'log', 'HOM loggen mislukt', 'Contact HOM Log Error'];
        yield 'log royalty' => ['postActionLogRoyalty', 'logRoyalty', 'RI loggen mislukt', 'Contact RI Log Error'];
    }

    #[DataProvider('actions')]
    public function testPassesTheBodyToTheService(string $action, string $method, string $userMessage, string $logMessage): void
    {
        $body = (object) ['id' => 'c1', 'outcome' => 'attended'];

        $result = $this->api($method, null, $body)->$action($this->request($body));

        self::assertSame(['success' => true], $result);
    }

    #[DataProvider('actions')]
    public function testErrors(string $action, string $method, string $userMessage, string $logMessage): void
    {
        foreach ([new BadRequest('Ongeldig'), new Forbidden('Geen rechten'), new NotFound('Weg')] as $error) {
            try {
                $this->api($method, $error)->$action($this->request((object) []));
                self::fail('Expected ' . $error::class);
            } catch (Throwable $e) {
                self::assertSame($error, $e, 'Passed on as is');
            }
        }

        try {
            $this->api($method, new Conflict('Vol'))->$action($this->request((object) []));
            self::fail('Expected BadRequest');
        } catch (BadRequest $e) {
            self::assertSame('Helaas, dit tijdstip is zojuist volgeboekt. Kies een ander moment.', $e->getMessage());
        }

        try {
            $this->api($method, new RuntimeException('SQL error'))->$action($this->request((object) []));
            self::fail('Expected BadRequest');
        } catch (BadRequest $e) {
            self::assertSame("$userMessage: SQL error", $e->getMessage());
        }

        self::assertSame(["$logMessage: SQL error"], $this->logged);
    }

    private function request(object $body): Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getParsedBody')->willReturn($body);

        return $request;
    }

    private function api(string $method, ?Throwable $error = null, ?object $expectedBody = null): ContactHomApi
    {
        $service = $this->createStub(ContactHomService::class);
        $service->method($method)->willReturnCallback(function (object $data) use ($error, $expectedBody): array {
            if ($error) {
                throw $error;
            }

            self::assertSame($expectedBody, $data);

            return ['success' => true];
        });

        return new ContactHomApi($service);
    }
}
