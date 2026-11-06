<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Handlers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\HomRating;
use Espo\Modules\LeadManager\Handlers\IntroMeeting\AttendanceHandler;
use Espo\Modules\LeadManager\Handlers\IntroMeeting\CancelledHandler;
use Espo\Modules\LeadManager\Handlers\IntroMeeting\NoShowHandler;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class IntroMeetingHandlersTest extends HandlerTestCase
{
    private const DATE = '2026-01-10 10:00:00';

    /**
     * @return iterable<string, array{array<string, mixed>, ?HomRating, string, list<string>, list<string>, int}>
     */
    public static function standardAttendance(): iterable
    {
        yield 'first spark' => [
            ['cMeetingType' => 'spark', 'cSparksUsed' => 0], null, 'intro_attended',
            ["Afspraak: spark #1 aanwezig. Nog 1 sessie(s) beschikbaar.\nNotitie"],
            ['text:Opnieuw spark boeken of Kickstart'],
            1,
        ];
        yield 'second spark' => [
            ['cMeetingType' => 'spark', 'cSparksUsed' => 1], null, 'book_ks',
            ["Afspraak: spark #2 aanwezig. Alle sessies gebruikt. Klaar voor Kickstart!\nNotitie"],
            ['text:Kickstart boeken'],
            2,
        ];
        yield 'bws' => [
            ['cMeetingType' => 'bws'], null, 'book_ks',
            ["Afspraak: bws aanwezig\nNotitie"],
            ['text:Kickstart boeken'],
            0,
        ];
        yield 'hom A' => [
            ['cMeetingType' => 'hom'], HomRating::A, 'intro_attended',
            ["Afspraak: hom aanwezig\nHOM-uitkomst: A\nNotitie"],
            ['text:SPARK of Kickstart boeken'],
            0,
        ];
        yield 'hom D via legacy iom type' => [
            ['cMeetingType' => 'iom'], HomRating::D, 'book_ri',
            ["Afspraak: hom aanwezig\nHOM-uitkomst: D\nNotitie"],
            ['text:RI boeken'],
            0,
        ];
    }

    /**
     * @param array<string, mixed> $leadValues
     * @param list<string> $notes
     * @param list<string> $followUps
     */
    #[DataProvider('standardAttendance')]
    public function testStandardAttendance(
        array $leadValues,
        ?HomRating $rating,
        string $stage,
        array $notes,
        array $followUps,
        int $sparksUsed,
    ): void {
        $lead = $this->lead(['cStage' => 'intro_scheduled', ...$leadValues]);

        $result = $this->handler(AttendanceHandler::class)->handle('lead1', [
            'eventDate' => self::DATE,
            'coachNote' => 'Notitie',
            'homRating' => $rating,
        ]);

        self::assertSame(['attend_intro@' . self::DATE], $this->events);
        self::assertSame($notes, $this->notes);
        self::assertSame($followUps, $this->followUps);
        self::assertSame(['held:meeting1'], $this->meetings);
        self::assertSame($stage, $lead->get('cStage'));
        self::assertSame($sparksUsed, (int) $lead->get('cSparksUsed'));
        self::assertSame(['ev1'], $result->eventIds);
    }

    public function testAttendanceRequiresIntroMeetingType(): void
    {
        $this->lead(['cMeetingType' => 'kickstart']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lead does not have an intro meeting type set');

        $this->handler(AttendanceHandler::class)->handle('lead1', []);
    }

    public function testHomRatingOnlyForHom(): void
    {
        $this->lead(['cMeetingType' => 'spark']);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('HOM-uitkomst is enkel mogelijk bij een HOM.');

        $this->handler(AttendanceHandler::class)->handle('lead1', ['homRating' => HomRating::A]);
    }

    public function testHomRequiresRating(): void
    {
        $this->lead(['cMeetingType' => 'hom']);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('HOM-uitkomst (A/B/C/D) is verplicht.');

        $this->handler(AttendanceHandler::class)->handle('lead1', []);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?HomRating, string, string, string, string, string}>
     */
    public static function immediateBookings(): iterable
    {
        yield 'kickstart after spark' => [
            ['cMeetingType' => 'spark', 'cSparksUsed' => 0], null, 'kickstart',
            'kickstart_booked', 'ks_planned', 'kickstart', 'Direct Kickstart geboekt na spark',
        ];
        yield 'online kickstart after bws' => [
            ['cMeetingType' => 'bws'], null, 'online',
            'kickstart_booked', 'ks_planned', 'online', 'Direct Online Kickstart geboekt na bws',
        ];
        yield 'second spark after first' => [
            ['cMeetingType' => 'spark', 'cSparksUsed' => 0], null, 'spark',
            'book_intro', 'intro_scheduled', 'spark', 'Direct spark geboekt',
        ];
        yield 'kickstart after hom B' => [
            ['cMeetingType' => 'hom'], HomRating::B, 'kickstart',
            'kickstart_booked', 'ks_planned', 'kickstart', 'Direct Kickstart geboekt na hom',
        ];
        yield 'royalty after hom C' => [
            ['cMeetingType' => 'hom'], HomRating::C, 'royalty',
            'royalty_booked', 'ri_planned', 'royalty', 'Direct RI geboekt na hom',
        ];
    }

    /**
     * @param array<string, mixed> $leadValues
     */
    #[DataProvider('immediateBookings')]
    public function testImmediateBooking(
        array $leadValues,
        ?HomRating $rating,
        string $nextCalendarType,
        string $bookingEvent,
        string $stage,
        string $meetingType,
        string $bookingNote,
    ): void {
        $lead = $this->lead(['cStage' => 'intro_scheduled', ...$leadValues]);
        $this->calendar('next', $nextCalendarType, 'volgende');

        $result = $this->handler(AttendanceHandler::class)->handle('lead1', [
            'eventDate' => self::DATE,
            'homRating' => $rating,
            'nextBooking' => ['calendarId' => 'volgende', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00'],
        ]);

        self::assertSame(['attend_intro@' . self::DATE, "$bookingEvent@" . self::DATE], $this->events);
        self::assertSame(['create:volgende 2026-01-20 19:00', 'held:meeting1'], $this->meetings);
        self::assertSame("Afspraak: $bookingNote", $this->notes[1]);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame($stage, $lead->get('cStage'));
        self::assertSame($meetingType, $lead->get('cMeetingType'));
        self::assertSame(['ev1', 'ev2'], $result->eventIds);
    }

    public function testImmediateBookingAttendanceNote(): void
    {
        $this->lead(['cMeetingType' => 'spark', 'cSparksUsed' => 0]);
        $this->calendar('next', 'kickstart', 'volgende');

        $this->handler(AttendanceHandler::class)->handle('lead1', [
            'coachNote' => 'Notitie',
            'nextBooking' => ['calendarId' => 'volgende', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00'],
        ]);

        // Unlike the standard attendance note, this one has no trailing period.
        self::assertSame("Afspraak: spark #1 aanwezig. Nog 1 sessie(s) beschikbaar\nNotitie", $this->notes[0]);
    }

    /**
     * @return iterable<string, array{string, ?HomRating, string, string}>
     */
    public static function forbiddenImmediateBookings(): iterable
    {
        yield 'royalty without hom' => ['bws', null, 'royalty', 'Een RI kan enkel geboekt worden na een HOM met uitkomst C of D.'];
        yield 'royalty after hom A' => ['hom', HomRating::A, 'royalty', 'Na een HOM met uitkomst A kan je enkel SPARK of Kickstart of Online Kickstart boeken.'];
        yield 'kickstart after hom D' => ['hom', HomRating::D, 'kickstart', 'Na een HOM met uitkomst D kan je enkel RI boeken.'];
        yield 'bws after hom B' => ['hom', HomRating::B, 'bws', 'Na een HOM met uitkomst B kan je enkel SPARK of Kickstart of Online Kickstart boeken.'];
    }

    #[DataProvider('forbiddenImmediateBookings')]
    public function testForbiddenImmediateBooking(string $attendedType, ?HomRating $rating, string $nextCalendarType, string $message): void
    {
        $lead = $this->lead(['cMeetingType' => $attendedType, 'cStage' => 'intro_scheduled']);
        $this->calendar('next', $nextCalendarType, 'volgende');

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        try {
            $this->handler(AttendanceHandler::class)->handle('lead1', [
                'homRating' => $rating,
                'nextBooking' => ['calendarId' => 'volgende', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00'],
            ]);
        } finally {
            self::assertSame([], $this->events);
            self::assertSame('intro_scheduled', $lead->get('cStage'));
        }
    }

    public function testImmediateBookingOnUnknownCalendarTypeThrowsAfterAttendanceIsRecorded(): void
    {
        $lead = $this->lead(['cMeetingType' => 'spark', 'cSparksUsed' => 0]);
        $this->calendar('next', 'general', 'volgende');

        try {
            $this->handler(AttendanceHandler::class)->handle('lead1', [
                'nextBooking' => ['calendarId' => 'volgende', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00'],
            ]);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            self::assertSame('Invalid next booking calendar type: general', $e->getMessage());
        }

        // The event, note, meeting and SPARK count were already written before the type check.
        self::assertSame(['attend_intro@-'], $this->events);
        self::assertSame(['create:volgende 2026-01-20 19:00'], $this->meetings);
        self::assertSame(1, $lead->get('cSparksUsed'));
    }

    public function testNoShowForSpark(): void
    {
        $lead = $this->lead(['cMeetingType' => 'spark', 'cSparksUsed' => 1, 'cStage' => 'intro_scheduled']);

        $this->handler(NoShowHandler::class)->handle('lead1', [
            'eventDate' => self::DATE,
            'coachNote' => 'Notitie',
            'callAgainDateTime' => '2026-01-12 18:00:00',
        ]);

        self::assertSame(['intro_no_show@' . self::DATE, 'call_again@' . self::DATE], $this->events);
        self::assertSame(["Afspraak: spark no-show. Nog 1 sessie(s) beschikbaar voor herboeking\nNotitie"], $this->notes);
        self::assertSame(['add:Niet opgedaagd spark - Opnieuw boeken@2026-01-12 18:00:00'], $this->followUps);
        self::assertSame(['notHeld:meeting1'], $this->meetings);
        self::assertSame('follow_up', $lead->get('cStage'));
    }

    public function testNoShowForHomWithoutFollowUpDate(): void
    {
        $this->lead(['cMeetingType' => 'hom']);
        $this->plannedMeeting = null;

        $this->handler(NoShowHandler::class)->handle('lead1', []);

        self::assertSame(['Afspraak: hom no-show'], $this->notes);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame([], $this->meetings);
    }

    public function testCancelRescheduleNow(): void
    {
        $lead = $this->lead(['cMeetingType' => 'spark', 'cSparksUsed' => 1]);
        // reschedule_now looks the calendar up by id: slugs are not resolved here.
        $this->calendar('cal1', 'bws');

        $result = $this->handler(CancelledHandler::class)->handle('lead1', [
            'eventDate' => self::DATE,
            'coachNote' => 'Notitie',
            'cancellationAction' => 'reschedule_now',
            'calendarId' => 'cal1',
            'selectedDate' => '2026-01-20',
            'selectedTime' => '19:00',
        ]);

        self::assertSame(['intro_cancelled@' . self::DATE, 'book_intro@' . self::DATE], $this->events);
        self::assertSame(["Afspraak: spark geannuleerd\nNotitie", 'Afspraak: bws opnieuw geboekt voor 2026-01-20 om 19:00'], $this->notes);
        self::assertSame(['cancelled:meeting1', 'create:cal1 2026-01-20 19:00'], $this->meetings);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame('intro_scheduled', $lead->get('cStage'));
        self::assertSame('bws', $lead->get('cMeetingType'));
        self::assertSame(['ev1', 'ev2'], $result->eventIds);
    }

    public function testCancelRescheduleNowOnNonIntroCalendarThrows(): void
    {
        $this->lead(['cMeetingType' => 'spark']);
        $this->calendar('cal1', 'kickstart');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Calendar type 'kickstart' is not an intro meeting");

        $this->handler(CancelledHandler::class)->handle('lead1', [
            'cancellationAction' => 'reschedule_now',
            'calendarId' => 'cal1',
            'selectedDate' => '2026-01-20',
            'selectedTime' => '19:00',
        ]);
    }

    public function testCancelRescheduleLater(): void
    {
        $lead = $this->lead(['cMeetingType' => 'bws', 'cStage' => 'intro_scheduled']);

        $this->handler(CancelledHandler::class)->handle('lead1', [
            'cancellationAction' => 'reschedule_later',
            'callAgainDateTime' => '2026-01-12 18:00:00',
        ]);

        self::assertSame(['intro_cancelled@-', 'call_again@-'], $this->events);
        self::assertSame(['add:bws geannuleerd - Opnieuw inplannen@2026-01-12 18:00:00'], $this->followUps);
        self::assertSame('follow_up', $lead->get('cStage'));
    }

    public function testCancelStopIsTheDefault(): void
    {
        $lead = $this->lead(['cMeetingType' => 'hom', 'cStage' => 'intro_scheduled']);

        $this->handler(CancelledHandler::class)->handle('lead1', []);

        // Unlike kickstart cancel_stop, the intro lead is not marked dead: it goes to follow-up.
        self::assertSame(['intro_cancelled@-'], $this->events);
        self::assertSame(['text:hom geannuleerd - Contact opnemen'], $this->followUps);
        self::assertSame('follow_up', $lead->get('cStage'));
    }
}
