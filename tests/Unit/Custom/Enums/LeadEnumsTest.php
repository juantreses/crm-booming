<?php

declare(strict_types=1);

namespace Tests\Unit\Custom\Enums;

use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\HomRating;
use Espo\Custom\Enums\IntroMeetingOutcome;
use Espo\Custom\Enums\IntroMeetingType;
use Espo\Custom\Enums\KickstartCalendarType;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\MessageSentOutcome;
use PHPUnit\Framework\TestCase;

final class LeadEnumsTest extends TestCase
{
    public function testHomRatingLeadsToRoyaltyOnlyForCAndD(): void
    {
        self::assertFalse(HomRating::A->leadsToRoyalty());
        self::assertFalse(HomRating::B->leadsToRoyalty());
        self::assertTrue(HomRating::C->leadsToRoyalty());
        self::assertTrue(HomRating::D->leadsToRoyalty());
    }

    public function testIntroMeetingTypeFromCalendarType(): void
    {
        self::assertSame(IntroMeetingType::SPARK, IntroMeetingType::fromCalendarType('spark'));
        self::assertSame(IntroMeetingType::BWS, IntroMeetingType::fromCalendarType('bws'));
        self::assertSame(IntroMeetingType::HOM, IntroMeetingType::fromCalendarType('hom'));
        // 'iom' is the legacy calendar type of the HOM.
        self::assertSame(IntroMeetingType::HOM, IntroMeetingType::fromCalendarType('iom'));
        self::assertNull(IntroMeetingType::fromCalendarType('kickstart'));
        self::assertNull(IntroMeetingType::fromCalendarType('SPARK'));

        self::assertTrue(IntroMeetingType::isIntroMeeting('iom'));
        self::assertFalse(IntroMeetingType::isIntroMeeting('royalty'));
    }

    public function testOnlySparkHasAUsageLimitOfTwo(): void
    {
        self::assertTrue(IntroMeetingType::SPARK->hasUsageLimit());
        self::assertSame(2, IntroMeetingType::SPARK->getMaxUsage());
        self::assertFalse(IntroMeetingType::BWS->hasUsageLimit());
        self::assertSame(1, IntroMeetingType::BWS->getMaxUsage());
        self::assertFalse(IntroMeetingType::HOM->hasUsageLimit());
    }

    public function testKickstartCalendarType(): void
    {
        self::assertTrue(KickstartCalendarType::isKickstart('kickstart'));
        self::assertTrue(KickstartCalendarType::isKickstart('online'));
        self::assertTrue(KickstartCalendarType::isKickstart('Kickstart'));
        self::assertFalse(KickstartCalendarType::isKickstart('royalty'));
        self::assertFalse(KickstartCalendarType::isKickstart(null));
        self::assertSame(['kickstart', 'online'], KickstartCalendarType::values());
        self::assertSame('Online Kickstart', KickstartCalendarType::ONLINE->getLabel());
    }

    public function testOutcomeIsValid(): void
    {
        self::assertTrue(CallOutcome::isValid('wrong_number'));
        self::assertFalse(CallOutcome::isValid('cancelled'));
        self::assertTrue(KickstartOutcome::isValid('still_thinking'));
        self::assertFalse(KickstartOutcome::isValid('called'));
        self::assertTrue(MessageSentOutcome::isValid('invited'));
        self::assertFalse(MessageSentOutcome::isValid('no_answer'));
        self::assertTrue(IntroMeetingOutcome::isValid('no_show'));
        self::assertFalse(IntroMeetingOutcome::isValid('Attended'));
        self::assertTrue(HomRating::isValid('B'));
        self::assertFalse(HomRating::isValid('b'));
    }
}
