<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Handlers;

use Espo\Modules\LeadManager\Handlers\Kickstart;
use Espo\Modules\LeadManager\Handlers\Royalty;
use InvalidArgumentException;
use RuntimeException;
use TypeError;

final class BookingAndCancellationHandlersTest extends HandlerTestCase
{
    private const BOOKING = [
        'eventDate' => '2026-01-10 10:00:00',
        'coachNote' => 'Notitie',
        'calendarId' => 'ks-gent',
        'selectedDate' => '2026-01-20',
        'selectedTime' => '19:00',
    ];

    public function testKickstartBooking(): void
    {
        $lead = $this->lead(['cStage' => 'book_ks']);
        $this->calendar('cal1', 'kickstart', 'ks-gent');

        $result = $this->handler(Kickstart\BookedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame(['create:ks-gent 2026-01-20 19:00'], $this->meetings);
        self::assertSame(['kickstart_booked@2026-01-10 10:00:00'], $this->events);
        self::assertSame(['Afspraak: Notitie'], $this->notes);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame('ks_planned', $lead->get('cStage'));
        self::assertSame('kickstart', $lead->get('cMeetingType'));
        self::assertSame(['ev1'], $result->eventIds);
    }

    public function testOnlineKickstartBookingKeepsOnlineMeetingType(): void
    {
        $lead = $this->lead();
        $this->calendar('cal1', 'online', 'ks-gent');

        $this->handler(Kickstart\BookedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame('online', $lead->get('cMeetingType'));
        self::assertSame('ks_planned', $lead->get('cStage'));
    }

    public function testRoyaltyBooking(): void
    {
        $lead = $this->lead();
        $this->calendar('cal1', 'royalty', 'ks-gent');

        $this->handler(Royalty\BookedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame(['royalty_booked@2026-01-10 10:00:00'], $this->events);
        self::assertSame('ri_planned', $lead->get('cStage'));
        self::assertSame('royalty', $lead->get('cMeetingType'));
    }

    public function testKickstartBookingRejectsRoyaltyCalendar(): void
    {
        $this->lead();
        $this->calendar('cal1', 'royalty', 'ks-gent');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Calendar type 'royalty' is not kickstart");

        try {
            $this->handler(Kickstart\BookedHandler::class)->handle('lead1', self::BOOKING);
        } finally {
            self::assertSame([], $this->meetings);
            self::assertSame([], $this->events);
        }
    }

    public function testRoyaltyBookingRejectsKickstartCalendar(): void
    {
        $this->lead();
        $this->calendar('cal1', 'kickstart', 'ks-gent');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Calendar type 'kickstart' is not royalty");

        $this->handler(Royalty\BookedHandler::class)->handle('lead1', self::BOOKING);
    }

    public function testBookingRequiresCalendarDateAndTime(): void
    {
        $this->lead();

        $this->expectException(InvalidArgumentException::class);

        $this->handler(Kickstart\BookedHandler::class)->handle('lead1', ['calendarId' => 'ks-gent', 'selectedDate' => '2026-01-20']);
    }

    public function testBookingForUnknownLeadThrows(): void
    {
        $this->calendar('cal1', 'kickstart', 'ks-gent');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lead not found: lead1');

        $this->handler(Kickstart\BookedHandler::class)->handle('lead1', self::BOOKING);
    }

    public function testBookingWithUnknownCalendarSlugFailsWithTypeError(): void
    {
        // SlugService returns null for an unknown slug and getEntityById() requires a string,
        // so the "Calendar not found" message is never reached.
        $this->lead();

        $this->expectException(TypeError::class);

        $this->handler(Kickstart\BookedHandler::class)->handle('lead1', self::BOOKING);
    }

    public function testKickstartCancelRescheduleNow(): void
    {
        $lead = $this->lead(['cMeetingType' => 'kickstart']);
        $this->calendar('cal1', 'kickstart', 'ks-gent');

        $result = $this->handler(Kickstart\CancelledHandler::class)->handle('lead1', [
            ...self::BOOKING,
            'cancellationAction' => 'reschedule_now',
        ]);

        self::assertSame(['kickstart_cancelled@2026-01-10 10:00:00', 'kickstart_booked@2026-01-10 10:00:00'], $this->events);
        self::assertSame(['cancelled:meeting1', 'create:ks-gent 2026-01-20 19:00'], $this->meetings);
        self::assertSame(['Kickstart: Notitie'], $this->notes);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame('kickstart', $lead->get('cMeetingType'));
        self::assertSame([], $this->savedEntities());
        self::assertSame(['ev1', 'ev2'], $result->eventIds);
    }

    public function testKickstartRescheduledOnOnlineCalendarSwitchesMeetingType(): void
    {
        $lead = $this->lead(['cMeetingType' => 'kickstart']);
        $this->calendar('cal1', 'online', 'ks-gent');

        $this->handler(Kickstart\CancelledHandler::class)->handle('lead1', [...self::BOOKING, 'cancellationAction' => 'reschedule_now']);

        self::assertSame('online', $lead->get('cMeetingType'));
        self::assertSame(['Lead:lead1'], $this->savedEntities());
    }

    public function testRescheduleNowWithoutDateOnlyCancels(): void
    {
        $this->lead();

        $this->handler(Kickstart\CancelledHandler::class)->handle('lead1', ['cancellationAction' => 'reschedule_now', 'calendarId' => 'ks-gent']);

        self::assertSame(['kickstart_cancelled@-'], $this->events);
        self::assertSame(['cancelled:meeting1'], $this->meetings);
        self::assertSame([], $this->followUps);
    }

    public function testKickstartCancelRescheduleLater(): void
    {
        $this->lead();

        $this->handler(Kickstart\CancelledHandler::class)->handle('lead1', [
            'cancellationAction' => 'reschedule_later',
            'callAgainDateTime' => '2026-01-15 18:00:00',
        ]);

        self::assertSame(['kickstart_cancelled@-', 'call_again@-'], $this->events);
        self::assertSame(['add:Kickstart geannuleerd - Opnieuw inplannen@2026-01-15 18:00:00'], $this->followUps);
    }

    public function testKickstartCancelStop(): void
    {
        $this->lead();

        $this->handler(Kickstart\CancelledHandler::class)->handle('lead1', ['cancellationAction' => 'cancel_stop']);

        self::assertSame(['kickstart_cancelled@-', 'cancelled_stop@-'], $this->events);
        self::assertSame(['clear'], $this->followUps);
    }

    public function testKickstartCancelWithoutActionOnlyCancels(): void
    {
        $this->lead();

        $this->handler(Kickstart\CancelledHandler::class)->handle('lead1', []);

        self::assertSame(['kickstart_cancelled@-'], $this->events);
        self::assertSame(['cancelled:meeting1'], $this->meetings);
        self::assertSame([], $this->followUps);
    }

    public function testRoyaltyCancelRescheduleNow(): void
    {
        $this->lead(['cMeetingType' => 'royalty']);
        $this->calendar('cal1', 'royalty', 'ks-gent');

        $this->handler(Royalty\CancelledHandler::class)->handle('lead1', [
            ...self::BOOKING,
            'cancellationAction' => 'reschedule_now',
        ]);

        self::assertSame(['royalty_cancelled@2026-01-10 10:00:00', 'royalty_booked@2026-01-10 10:00:00'], $this->events);
        self::assertSame(['Royalty: Notitie'], $this->notes);
    }

    public function testRoyaltyCancelRescheduleLater(): void
    {
        $this->lead();

        $this->handler(Royalty\CancelledHandler::class)->handle('lead1', [
            'cancellationAction' => 'reschedule_later',
            'callAgainDateTime' => '2026-01-15 18:00:00',
        ]);

        self::assertSame(['royalty_cancelled@-', 'call_again@-'], $this->events);
        self::assertSame(['add:RI geannuleerd - Opnieuw inplannen@2026-01-15 18:00:00'], $this->followUps);
    }
}
