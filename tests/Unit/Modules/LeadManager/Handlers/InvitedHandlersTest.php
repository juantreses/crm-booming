<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Handlers;

use Espo\Modules\LeadManager\Handlers\Call;
use Espo\Modules\LeadManager\Handlers\IntroMeeting;
use Espo\Modules\LeadManager\Handlers\Message;
use Espo\Modules\LeadManager\Handlers\OutcomeHandler;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * "Invited" outcomes book a meeting; what happens next depends on the calendar type.
 */
final class InvitedHandlersTest extends HandlerTestCase
{
    private const BOOKING = [
        'eventDate' => '2026-01-10 10:00:00',
        'coachNote' => 'Notitie',
        'calendarId' => 'agenda',
        'selectedDate' => '2026-01-20',
        'selectedTime' => '19:00',
    ];

    /**
     * calendar type => [booking event, stage, meeting type]
     *
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function calendarTypes(): iterable
    {
        yield 'kickstart' => ['kickstart', 'kickstart_booked', 'ks_planned', 'kickstart'];
        yield 'online kickstart' => ['online', 'kickstart_booked', 'ks_planned', 'online'];
        yield 'royalty' => ['royalty', 'royalty_booked', 'ri_planned', 'royalty'];
        yield 'spark' => ['spark', 'book_intro', 'intro_scheduled', 'spark'];
        yield 'bws' => ['bws', 'book_intro', 'intro_scheduled', 'bws'];
        yield 'hom (legacy iom calendar)' => ['iom', 'book_intro', 'intro_scheduled', 'hom'];
    }

    #[DataProvider('calendarTypes')]
    public function testCallInvited(string $calendarType, string $event, string $stage, string $meetingType): void
    {
        $lead = $this->lead(['cStage' => 'to_call']);
        $this->calendar('cal1', $calendarType, 'agenda');

        $result = $this->handler(Call\InvitedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame(['called@2026-01-10 10:00:00', "$event@" . self::BOOKING['eventDate']], $this->events);
        self::assertSame(['Telefoon: Notitie'], $this->notes);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame(['create:agenda 2026-01-20 19:00'], $this->meetings);
        self::assertSame($stage, $lead->get('cStage'));
        self::assertSame($meetingType, $lead->get('cMeetingType'));
        self::assertSame(['ev1', 'ev2'], $result->eventIds);
    }

    public function testCallInvitedOnOtherCalendarOnlyBooksTheMeeting(): void
    {
        $lead = $this->lead(['cStage' => 'to_call']);
        $this->calendar('cal1', 'general', 'agenda');

        $this->handler(Call\InvitedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame(['called@2026-01-10 10:00:00'], $this->events);
        self::assertSame(['create:agenda 2026-01-20 19:00'], $this->meetings);
        self::assertSame('to_call', $lead->get('cStage'));
    }

    public function testCallInvitedWithoutSlotOnlyLogsTheCall(): void
    {
        $this->lead();

        $this->handler(Call\InvitedHandler::class)->handle('lead1', ['calendarId' => 'agenda']);

        self::assertSame(['called@-'], $this->events);
        self::assertSame([], $this->meetings);
    }

    #[DataProvider('calendarTypes')]
    public function testMessageInvited(string $calendarType, string $event, string $stage, string $meetingType): void
    {
        $lead = $this->lead(['cStage' => 'message_sent']);
        $this->calendar('cal1', $calendarType, 'agenda');

        $this->handler(Message\InvitedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame(["$event@" . self::BOOKING['eventDate']], $this->events);
        self::assertSame(['Bericht: Notitie'], $this->notes);
        self::assertSame(['create:agenda 2026-01-20 19:00'], $this->meetings);
        self::assertSame($stage, $lead->get('cStage'));
        self::assertSame($meetingType, $lead->get('cMeetingType'));
    }

    public function testMessageInvitedOnOtherCalendarLogsBookIntroWithoutStageChange(): void
    {
        $lead = $this->lead(['cStage' => 'message_sent']);
        $this->calendar('cal1', 'general', 'agenda');

        $this->handler(Message\InvitedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame(['book_intro@2026-01-10 10:00:00'], $this->events);
        self::assertSame('message_sent', $lead->get('cStage'));
    }

    public function testMessageInvitedWithoutSlotLogsNothing(): void
    {
        $this->lead();

        $result = $this->handler(Message\InvitedHandler::class)->handle('lead1', []);

        self::assertSame([], $this->events);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame([], $result->eventIds);
    }

    /**
     * @return iterable<string, array{class-string<OutcomeHandler>}>
     */
    public static function invitedHandlers(): iterable
    {
        yield 'call' => [Call\InvitedHandler::class];
        yield 'message' => [Message\InvitedHandler::class];
        yield 'intro meeting' => [IntroMeeting\InvitedHandler::class];
    }

    /**
     * @param class-string<OutcomeHandler> $class
     */
    #[DataProvider('invitedHandlers')]
    public function testThirdSparkCannotBeBooked(string $class): void
    {
        $this->lead(['cSparksUsed' => 2]);
        $this->calendar('cal1', 'spark', 'agenda');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lead cannot book spark');

        try {
            $this->handler($class)->handle('lead1', self::BOOKING);
        } finally {
            self::assertSame([], $this->meetings);
        }
    }

    public function testIntroInvitedBooksSecondSpark(): void
    {
        $lead = $this->lead(['cSparksUsed' => 1, 'cStage' => 'intro_attended']);
        $this->calendar('cal1', 'spark', 'agenda');

        $result = $this->handler(IntroMeeting\InvitedHandler::class)->handle('lead1', self::BOOKING);

        self::assertSame(['create:agenda 2026-01-20 19:00'], $this->meetings);
        self::assertSame(['book_intro@2026-01-10 10:00:00'], $this->events);
        self::assertSame(["Afspraak: spark #2 geboekt voor 2026-01-20 om 19:00\nNotitie"], $this->notes);
        self::assertSame(['clear'], $this->followUps);
        self::assertSame('intro_scheduled', $lead->get('cStage'));
        self::assertSame('spark', $lead->get('cMeetingType'));
        self::assertSame(['ev1'], $result->eventIds);
    }

    public function testIntroInvitedFromCallUsesPhoneSource(): void
    {
        $this->lead();
        $this->calendar('cal1', 'bws', 'agenda');

        $this->handler(IntroMeeting\InvitedHandler::class)->handle('lead1', [...self::BOOKING, 'coachNote' => null, 'fromCall' => true]);

        self::assertSame(['Telefoon: bws geboekt voor 2026-01-20 om 19:00'], $this->notes);
    }

    public function testIntroInvitedRejectsNonIntroCalendar(): void
    {
        $this->lead();
        $this->calendar('cal1', 'kickstart', 'agenda');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Calendar type 'kickstart' is not an intro meeting");

        $this->handler(IntroMeeting\InvitedHandler::class)->handle('lead1', self::BOOKING);
    }

    public function testIntroInvitedRequiresSlot(): void
    {
        $this->lead();

        $this->expectException(InvalidArgumentException::class);

        $this->handler(IntroMeeting\InvitedHandler::class)->handle('lead1', ['calendarId' => 'agenda']);
    }
}
