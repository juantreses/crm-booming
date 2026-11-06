<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\ValueObjects;

use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\HomRating;
use Espo\Custom\Enums\IntroMeetingOutcome;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\MessageSentOutcome;
use Espo\Modules\LeadManager\ValueObjects\CallOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\IntroMeetingOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\KickstartFollowUpOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\KickstartOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\MessageOutcomeData;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use PHPUnit\Framework\TestCase;
use ValueError;

final class OutcomeDataTest extends TestCase
{
    public function testCallOutcomeDataFromStdClass(): void
    {
        $data = CallOutcomeData::fromStdClass((object) [
            'id' => 'lead1',
            'outcome' => 'invited',
            'callDateTime' => '2026-01-01 10:00:00',
            'callAgainDateTime' => '2026-01-02 10:00:00',
            'coachNote' => 'Note',
            'calendarId' => 'kickstart-gent',
            'selectedDate' => '2026-01-10',
            'selectedTime' => '19:00',
        ]);

        self::assertSame('lead1', $data->leadId);
        self::assertSame(CallOutcome::INVITED, $data->outcome);
        self::assertSame('2026-01-01 10:00:00', $data->callDateTime);
        self::assertSame('2026-01-02 10:00:00', $data->callAgainDateTime);
        self::assertSame('Note', $data->coachNote);
        self::assertSame('kickstart-gent', $data->calendarId);
        self::assertSame('2026-01-10', $data->selectedDate);
        self::assertSame('19:00', $data->selectedTime);
    }

    public function testCallOutcomeDataOptionalFieldsDefaultToNull(): void
    {
        $data = CallOutcomeData::fromStdClass((object) ['id' => 123, 'outcome' => 'called']);

        self::assertSame('123', $data->leadId);
        self::assertNull($data->callDateTime);
        self::assertNull($data->coachNote);
        self::assertNull($data->calendarId);
    }

    public function testUnknownOutcomeThrows(): void
    {
        $this->expectException(ValueError::class);

        CallOutcomeData::fromStdClass((object) ['id' => 'lead1', 'outcome' => 'bogus']);
    }

    public function testKickstartOutcomeDataFromStdClass(): void
    {
        $data = KickstartOutcomeData::fromStdClass((object) [
            'id' => 'lead1',
            'outcome' => 'cancelled',
            'kickstartDateTime' => '2026-01-01 10:00:00',
            'cancellationAction' => 'reschedule_now',
            'calendarId' => 'cal1',
            'selectedDate' => '2026-01-10',
            'selectedTime' => '19:00',
        ]);

        self::assertSame(KickstartOutcome::CANCELLED, $data->outcome);
        self::assertSame('2026-01-01 10:00:00', $data->kickstartDateTime);
        self::assertSame('reschedule_now', $data->cancellationAction);
        self::assertNull($data->callAgainDateTime);
        self::assertSame('cal1', $data->calendarId);
    }

    public function testKickstartFollowUpOutcomeDataFromStdClass(): void
    {
        $data = KickstartFollowUpOutcomeData::fromStdClass((object) [
            'id' => 'lead1',
            'outcome' => 'became_client',
            'followUpDateTime' => '2026-01-01 10:00:00',
            'coachNote' => 'Yes',
        ]);

        self::assertSame(KickstartOutcome::BECAME_CLIENT, $data->outcome);
        self::assertSame('2026-01-01 10:00:00', $data->followUpDateTime);
        self::assertSame('Yes', $data->coachNote);
    }

    public function testMessageOutcomeDataFromStdClass(): void
    {
        $data = MessageOutcomeData::fromStdClass((object) [
            'id' => 'lead1',
            'outcome' => 'call_again',
            'callAgainDateTime' => '2026-01-02 10:00:00',
            'meetingType' => 'spark',
        ]);

        self::assertSame(MessageSentOutcome::CALL_AGAIN, $data->outcome);
        self::assertSame('2026-01-02 10:00:00', $data->callAgainDateTime);
        self::assertSame('spark', $data->meetingType);
    }

    public function testIntroMeetingOutcomeDataFromStdClass(): void
    {
        $data = IntroMeetingOutcomeData::fromStdClass((object) [
            'id' => 'lead1',
            'outcome' => 'attended',
            'introDateTime' => '2026-01-01 10:00:00',
            'homRating' => 'C',
        ]);

        self::assertSame(IntroMeetingOutcome::ATTENDED, $data->outcome);
        self::assertSame('2026-01-01 10:00:00', $data->introDateTime);
        self::assertSame(HomRating::C, $data->homRating);
        self::assertNull($data->nextBooking);
    }

    public function testIntroMeetingEmptyHomRatingIsNull(): void
    {
        $data = IntroMeetingOutcomeData::fromStdClass((object) ['id' => 'lead1', 'outcome' => 'attended', 'homRating' => '']);

        self::assertNull($data->homRating);
    }

    public function testIntroMeetingNextBookingFromObject(): void
    {
        $data = IntroMeetingOutcomeData::fromStdClass((object) [
            'id' => 'lead1',
            'outcome' => 'attended',
            'nextBooking' => (object) ['calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00', 'extra' => 'x'],
        ]);

        self::assertSame(['calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'], $data->nextBooking);
    }

    public function testIntroMeetingNextBookingFromArray(): void
    {
        $data = IntroMeetingOutcomeData::fromStdClass((object) [
            'id' => 'lead1',
            'outcome' => 'attended',
            'nextBooking' => ['calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'],
        ]);

        self::assertSame(['calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'], $data->nextBooking);
    }

    public function testIncompleteNextBookingIsDropped(): void
    {
        foreach (['calendarId', 'selectedDate', 'selectedTime'] as $missing) {
            $nextBooking = ['calendarId' => 'cal1', 'selectedDate' => '2026-01-10', 'selectedTime' => '19:00'];
            unset($nextBooking[$missing]);

            $data = IntroMeetingOutcomeData::fromStdClass((object) [
                'id' => 'lead1',
                'outcome' => 'attended',
                'nextBooking' => (object) $nextBooking,
            ]);

            self::assertNull($data->nextBooking, "nextBooking without $missing");
        }
    }

    public function testNextBookingOfAnotherTypeIsIgnored(): void
    {
        $data = IntroMeetingOutcomeData::fromStdClass((object) ['id' => 'lead1', 'outcome' => 'attended', 'nextBooking' => 'cal1']);

        self::assertNull($data->nextBooking);
    }

    public function testOutcomeResultIsImmutable(): void
    {
        $empty = new OutcomeResult();
        $one = $empty->addEventId('ev1');
        $two = $one->addEventId('ev2');

        self::assertSame(['success' => true, 'eventIds' => []], $empty->toArray());
        self::assertSame(['success' => true, 'eventIds' => ['ev1']], $one->toArray());
        self::assertSame(['success' => true, 'eventIds' => ['ev1', 'ev2']], $two->toArray());
    }

    public function testOutcomeResultKeepsSuccessFlag(): void
    {
        $result = (new OutcomeResult([], false))->addEventId('ev1');

        self::assertSame(['success' => false, 'eventIds' => ['ev1']], $result->toArray());
    }
}
