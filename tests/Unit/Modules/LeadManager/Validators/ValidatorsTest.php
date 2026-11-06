<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Validators;

use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\LeadManager\Validators\LogCallValidator;
use Espo\Modules\LeadManager\Validators\LogIntroMeetingValidator;
use Espo\Modules\LeadManager\Validators\LogKickstartBookingValidator;
use Espo\Modules\LeadManager\Validators\LogKickstartValidator;
use Espo\Modules\LeadManager\Validators\LogMessageOutcomeValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorsTest extends TestCase
{
    private static function past(): string
    {
        return gmdate('Y-m-d H:i:s', time() - 3600);
    }

    private static function future(): string
    {
        return gmdate('Y-m-d H:i:s', time() + 86400);
    }

    /**
     * @return iterable<string, array{object, string}>
     */
    public static function invalidCalls(): iterable
    {
        yield 'no id' => [(object) ['outcome' => 'called'], 'Lead ID is required.'];
        yield 'empty id' => [(object) ['id' => '', 'outcome' => 'called'], 'Lead ID is required.'];
        yield 'no outcome' => [(object) ['id' => 'l1'], 'Invalid call outcome.'];
        yield 'unknown outcome' => [(object) ['id' => 'l1', 'outcome' => 'bogus'], 'Invalid call outcome.'];
        yield 'call in the future' => [
            (object) ['id' => 'l1', 'outcome' => 'called', 'callDateTime' => self::future()],
            'Gesprek datum/tijd mag niet in de toekomst zijn.',
        ];
        yield 'call again without date' => [
            (object) ['id' => 'l1', 'outcome' => 'call_again'],
            'Datum/tijd opnieuw bellen is verplicht.',
        ];
        yield 'call again in the past' => [
            (object) ['id' => 'l1', 'outcome' => 'call_again', 'callAgainDateTime' => self::past()],
            'Datum/tijd opnieuw bellen moet in de toekomst zijn.',
        ];
        yield 'invited without calendar' => [
            (object) ['id' => 'l1', 'outcome' => 'invited', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'],
            'Geen agenda geselecteerd.',
        ];
        yield 'invited without time' => [
            (object) ['id' => 'l1', 'outcome' => 'invited', 'calendarId' => 'cal1', 'selectedDate' => '2026-01-10'],
            'Geen tijdstip geselecteerd.',
        ];
    }

    #[DataProvider('invalidCalls')]
    public function testLogCallRejects(object $data, string $message): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        (new LogCallValidator())->validate($data);
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function validCalls(): iterable
    {
        yield 'called' => [(object) ['id' => 'l1', 'outcome' => 'called']];
        yield 'called in the past' => [(object) ['id' => 'l1', 'outcome' => 'called', 'callDateTime' => self::past()]];
        yield 'no answer' => [(object) ['id' => 'l1', 'outcome' => 'no_answer']];
        yield 'call again in the future' => [(object) ['id' => 'l1', 'outcome' => 'call_again', 'callAgainDateTime' => self::future()]];
        yield 'invited' => [(object) [
            'id' => 'l1', 'outcome' => 'invited', 'calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00',
        ]];
        // Only call_again checks callAgainDateTime.
        yield 'called with past call again date' => [(object) ['id' => 'l1', 'outcome' => 'called', 'callAgainDateTime' => self::past()]];
    }

    #[DataProvider('validCalls')]
    public function testLogCallAccepts(object $data): void
    {
        $this->expectNotToPerformAssertions();

        (new LogCallValidator())->validate($data);
    }

    /**
     * @return iterable<string, array{object, string}>
     */
    public static function invalidKickstarts(): iterable
    {
        yield 'no id' => [(object) ['outcome' => 'no_show'], 'Lead ID is required.'];
        yield 'unknown outcome' => [(object) ['id' => 'l1', 'outcome' => 'bogus'], 'Invalid call outcome.'];
        yield 'kickstart in the future' => [
            (object) ['id' => 'l1', 'outcome' => 'no_show', 'kickstartDateTime' => self::future()],
            'Kickstart datum/tijd mag niet in de toekomst zijn.',
        ];
        yield 'cancelled without action' => [(object) ['id' => 'l1', 'outcome' => 'cancelled'], 'Vervolgactie is verplicht bij annulering.'];
        yield 'reschedule now without date' => [
            (object) ['id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'reschedule_now'],
            'Nieuwe afspraakdatum is verplicht.',
        ];
        yield 'reschedule later without date' => [
            (object) ['id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'reschedule_later'],
            'Datum voor terugbellen is verplicht.',
        ];
        yield 'still thinking without date' => [
            (object) ['id' => 'l1', 'outcome' => 'still_thinking'],
            'Datum/tijd opnieuw bellen is verplicht.',
        ];
        yield 'call again in the past' => [
            (object) ['id' => 'l1', 'outcome' => 'still_thinking', 'callAgainDateTime' => self::past()],
            'Datum/tijd opnieuw bellen moet in de toekomst zijn.',
        ];
    }

    #[DataProvider('invalidKickstarts')]
    public function testLogKickstartRejects(object $data, string $message): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        (new LogKickstartValidator())->validate($data);
    }

    public function testLogKickstartUsesMeetingLabelInMessage(): void
    {
        $this->expectExceptionMessage('RI datum/tijd mag niet in de toekomst zijn.');

        (new LogKickstartValidator('RI'))->validate((object) ['id' => 'l1', 'outcome' => 'no_show', 'kickstartDateTime' => self::future()]);
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function validKickstarts(): iterable
    {
        yield 'became client' => [(object) ['id' => 'l1', 'outcome' => 'became_client', 'kickstartDateTime' => self::past()]];
        yield 'cancel stop' => [(object) ['id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'cancel_stop']];
        // reschedule_now only requires a date, not a calendar or time.
        yield 'reschedule now' => [(object) ['id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'reschedule_now', 'selectedDate' => '2026-01-10']];
        yield 'reschedule later' => [(object) [
            'id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'reschedule_later', 'callAgainDateTime' => self::future(),
        ]];
        yield 'still thinking' => [(object) ['id' => 'l1', 'outcome' => 'still_thinking', 'callAgainDateTime' => self::future()]];
    }

    #[DataProvider('validKickstarts')]
    public function testLogKickstartAccepts(object $data): void
    {
        $this->expectNotToPerformAssertions();

        (new LogKickstartValidator())->validate($data);
    }

    /**
     * @return iterable<string, array{object, string}>
     */
    public static function invalidKickstartBookings(): iterable
    {
        yield 'no id' => [(object) ['calendarId' => 'cal1'], 'Lead ID is required.'];
        yield 'no calendar' => [(object) ['id' => 'l1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'], 'Geen agenda geselecteerd.'];
        yield 'no date' => [(object) ['id' => 'l1', 'calendarId' => 'cal1', 'selectedTime' => '19:00'], 'Geen tijdstip geselecteerd.'];
        yield 'no time' => [(object) ['id' => 'l1', 'calendarId' => 'cal1', 'selectedDate' => '2026-01-10'], 'Geen tijdstip geselecteerd.'];
    }

    #[DataProvider('invalidKickstartBookings')]
    public function testLogKickstartBookingRejects(object $data, string $message): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        (new LogKickstartBookingValidator())->validate($data);
    }

    public function testLogKickstartBookingAccepts(): void
    {
        $this->expectNotToPerformAssertions();

        (new LogKickstartBookingValidator())->validate((object) [
            'id' => 'l1', 'calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00',
        ]);
    }

    /**
     * @return iterable<string, array{object, string}>
     */
    public static function invalidMessages(): iterable
    {
        yield 'no id' => [(object) ['outcome' => 'invited'], 'Lead ID is required.'];
        yield 'unknown outcome' => [(object) ['id' => 'l1', 'outcome' => 'called'], 'Invalid message outcome.'];
        yield 'call again without date' => [(object) ['id' => 'l1', 'outcome' => 'call_again'], 'Datum/tijd opnieuw bellen is verplicht.'];
        yield 'call again in the past' => [
            (object) ['id' => 'l1', 'outcome' => 'call_again', 'callAgainDateTime' => self::past()],
            'Datum/tijd opnieuw bellen moet in de toekomst zijn.',
        ];
        yield 'invited without calendar' => [(object) ['id' => 'l1', 'outcome' => 'invited'], 'Geen agenda geselecteerd.'];
        yield 'invited without time' => [
            (object) ['id' => 'l1', 'outcome' => 'invited', 'calendarId' => 'cal1', 'selectedDate' => '2026-01-10'],
            'Geen tijdstip geselecteerd.',
        ];
    }

    #[DataProvider('invalidMessages')]
    public function testLogMessageOutcomeRejects(object $data, string $message): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        (new LogMessageOutcomeValidator())->validate($data);
    }

    public function testLogMessageOutcomeAccepts(): void
    {
        $this->expectNotToPerformAssertions();

        $validator = new LogMessageOutcomeValidator();
        $validator->validate((object) ['id' => 'l1', 'outcome' => 'not_interested']);
        $validator->validate((object) ['id' => 'l1', 'outcome' => 'call_again', 'callAgainDateTime' => self::future()]);
        $validator->validate((object) ['id' => 'l1', 'outcome' => 'invited', 'calendarId' => 'c', 'selectedDate' => 'd', 'selectedTime' => 't']);
    }

    /**
     * @return iterable<string, array{object, string}>
     */
    public static function invalidIntroMeetings(): iterable
    {
        $next = (object) ['calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'];

        yield 'no id' => [(object) ['outcome' => 'attended'], 'Lead ID is required.'];
        yield 'unknown outcome' => [(object) ['id' => 'l1', 'outcome' => 'became_client'], 'Invalid intro meeting outcome.'];
        yield 'intro in the future' => [
            (object) ['id' => 'l1', 'outcome' => 'attended', 'introDateTime' => self::future()],
            'Intro datum/tijd mag niet in de toekomst zijn.',
        ];
        yield 'cancelled without action' => [(object) ['id' => 'l1', 'outcome' => 'cancelled'], 'Vervolgactie is verplicht bij annulering.'];
        yield 'reschedule now without time' => [
            (object) ['id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'reschedule_now', 'calendarId' => 'c', 'selectedDate' => 'd'],
            'Nieuwe afspraakdatum en tijd zijn verplicht.',
        ];
        yield 'reschedule later without date' => [
            (object) ['id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'reschedule_later'],
            'Datum voor terugbellen is verplicht.',
        ];
        yield 'incomplete next booking' => [
            (object) ['id' => 'l1', 'outcome' => 'attended', 'nextBooking' => (object) ['calendarId' => 'cal1']],
            'Volgende afspraak is niet volledig ingevuld.',
        ];
        yield 'hom rating after no show' => [
            (object) ['id' => 'l1', 'outcome' => 'no_show', 'homRating' => 'A'],
            'HOM-uitkomst is enkel mogelijk na een aanwezige HOM.',
        ];
        yield 'unknown hom rating' => [
            (object) ['id' => 'l1', 'outcome' => 'attended', 'homRating' => 'E'],
            'Ongeldige HOM-uitkomst.',
        ];
        yield 'next booking after no show' => [
            (object) ['id' => 'l1', 'outcome' => 'no_show', 'nextBooking' => $next],
            'Volgende afspraak is enkel mogelijk na een aanwezige intro.',
        ];
        yield 'call again in the past' => [
            (object) ['id' => 'l1', 'outcome' => 'no_show', 'callAgainDateTime' => self::past()],
            'Datum/tijd opnieuw bellen moet in de toekomst zijn.',
        ];
    }

    #[DataProvider('invalidIntroMeetings')]
    public function testLogIntroMeetingRejects(object $data, string $message): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        (new LogIntroMeetingValidator())->validate($data);
    }

    /**
     * @return iterable<string, array{object}>
     */
    public static function validIntroMeetings(): iterable
    {
        $next = (object) ['calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'];

        yield 'attended' => [(object) ['id' => 'l1', 'outcome' => 'attended', 'introDateTime' => self::past()]];
        yield 'attended with rating and next booking' => [(object) ['id' => 'l1', 'outcome' => 'attended', 'homRating' => 'D', 'nextBooking' => $next]];
        yield 'no show with call again' => [(object) ['id' => 'l1', 'outcome' => 'no_show', 'callAgainDateTime' => self::future()]];
        yield 'reschedule now' => [(object) [
            'id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'reschedule_now', 'calendarId' => 'c', 'selectedDate' => 'd', 'selectedTime' => 't',
        ]];
        yield 'cancel stop' => [(object) ['id' => 'l1', 'outcome' => 'cancelled', 'cancellationAction' => 'cancel_stop']];
    }

    #[DataProvider('validIntroMeetings')]
    public function testLogIntroMeetingAccepts(object $data): void
    {
        $this->expectNotToPerformAssertions();

        (new LogIntroMeetingValidator())->validate($data);
    }
}
