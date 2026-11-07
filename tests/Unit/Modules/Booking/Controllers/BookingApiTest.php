<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\Booking\Controllers\BookingApi;
use Espo\Modules\Booking\Services\BookingService;
use Espo\Modules\Booking\Services\MeetingCancellationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingApiTest extends TestCase
{
    /** @var list<array{string, mixed}> */
    private array $calls = [];

    public function testPublicBookingPassesBodyAsArray(): void
    {
        $result = $this->api()->postActionBooking($this->request(['calendarId' => 'spark-gent', 'email' => 'jan@test.be', 'time' => '19:00']));

        self::assertSame(['processBooking', ['calendarId' => 'spark-gent', 'email' => 'jan@test.be', 'time' => '19:00']], $this->calls[0]);
        self::assertSame(['ok' => 'processBooking'], $result);
    }

    public function testInternalBookingsAndCancellation(): void
    {
        $slot = ['entityType' => 'Contact', 'entityId' => 'c1', 'selectedDate' => '2026-01-20', 'selectedTime' => '18:00'];

        $this->api()->postActionBookWorkout($this->request($slot));
        $this->api()->postActionBookFollowUp($this->request($slot));
        $this->api()->postActionCancelMeeting($this->request(['id' => 'm1']));

        self::assertSame([['bookWorkout', $slot], ['bookFollowUp', $slot], ['cancelAndRemove', 'm1']], $this->calls);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function invalidRequests(): iterable
    {
        $slot = ['selectedDate' => '2026-01-20', 'selectedTime' => '18:00'];

        yield 'booking without calendar' => ['postActionBooking', ['email' => 'jan@test.be'], 'Onvoldoende gegevens voor de boeking.'];
        yield 'booking without email' => ['postActionBooking', ['calendarId' => 'c'], 'Onvoldoende gegevens voor de boeking.'];
        yield 'workout without entity' => ['postActionBookWorkout', $slot, 'Onvoldoende gegevens voor de boeking.'];
        yield 'workout without time' => [
            'postActionBookWorkout', ['entityType' => 'Lead', 'entityId' => 'l1', 'selectedDate' => '2026-01-20'], 'Geen tijdstip geselecteerd.',
        ];
        yield 'follow-up without entity id' => ['postActionBookFollowUp', ['entityType' => 'Contact', ...$slot], 'Onvoldoende gegevens voor de boeking.'];
        yield 'follow-up without date' => [
            'postActionBookFollowUp', ['entityType' => 'Contact', 'entityId' => 'c1', 'selectedTime' => '18:00'], 'Geen tijdstip geselecteerd.',
        ];
        yield 'cancel without id' => ['postActionCancelMeeting', [], 'Geen afspraak opgegeven.'];
    }

    /**
     * @param array<string, string> $body
     */
    #[DataProvider('invalidRequests')]
    public function testRejectsIncompleteRequest(string $action, array $body, string $message): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        try {
            $this->api()->$action($this->request($body));
        } finally {
            self::assertSame([], $this->calls);
        }
    }

    /**
     * @param array<string, string> $body
     */
    private function request(array $body): Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getParsedBody')->willReturn((object) $body);

        return $request;
    }

    private function api(): BookingApi
    {
        $bookingService = $this->createStub(BookingService::class);
        foreach (['processBooking', 'bookWorkout', 'bookFollowUp'] as $method) {
            $bookingService->method($method)->willReturnCallback(function (array $data) use ($method): array {
                $this->calls[] = [$method, $data];

                return ['ok' => $method];
            });
        }

        $cancellation = $this->createStub(MeetingCancellationService::class);
        $cancellation->method('cancelAndRemove')->willReturnCallback(function (string $id): array {
            $this->calls[] = ['cancelAndRemove', $id];

            return ['success' => true, 'mailError' => null];
        });

        return new BookingApi($bookingService, $cancellation);
    }
}
