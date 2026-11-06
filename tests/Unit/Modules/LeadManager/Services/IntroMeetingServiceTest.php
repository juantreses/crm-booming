<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Services;

use Espo\Custom\Enums\HomRating;
use Espo\Custom\Enums\IntroMeetingType;
use Espo\Custom\Enums\LeadStage;
use Espo\Modules\LeadManager\Services\IntroMeetingService;
use Espo\ORM\Entity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class IntroMeetingServiceTest extends TestCase
{
    use InMemoryEntityManager;

    public function testSparkCanBeBookedTwice(): void
    {
        $service = $this->service();

        self::assertTrue($service->canBook($this->lead(0), IntroMeetingType::SPARK));
        self::assertTrue($service->canBook($this->lead(1), IntroMeetingType::SPARK));
        self::assertFalse($service->canBook($this->lead(2), IntroMeetingType::SPARK));
    }

    public function testBwsAndHomHaveNoLimit(): void
    {
        $service = $this->service();

        self::assertTrue($service->canBook($this->lead(5), IntroMeetingType::BWS));
        self::assertTrue($service->canBook($this->lead(5), IntroMeetingType::HOM));
    }

    public function testUsageCountAndRemaining(): void
    {
        $service = $this->service();

        self::assertSame(1, $service->getUsageCount($this->lead(1), IntroMeetingType::SPARK));
        self::assertSame(0, $service->getUsageCount($this->lead(1), IntroMeetingType::BWS));
        self::assertSame(2, $service->getRemainingUsage($this->lead(0), IntroMeetingType::SPARK));
        self::assertSame(1, $service->getRemainingUsage($this->lead(1), IntroMeetingType::SPARK));
        self::assertSame(0, $service->getRemainingUsage($this->lead(2), IntroMeetingType::SPARK));
        self::assertSame(0, $service->getRemainingUsage($this->lead(3), IntroMeetingType::SPARK));
        self::assertSame(999, $service->getRemainingUsage($this->lead(0), IntroMeetingType::HOM));
    }

    /**
     * @return iterable<string, array{IntroMeetingType, int, ?HomRating, LeadStage, int}>
     */
    public static function attendance(): iterable
    {
        yield 'first spark' => [IntroMeetingType::SPARK, 0, null, LeadStage::INTRO_ATTENDED, 1];
        yield 'second spark' => [IntroMeetingType::SPARK, 1, null, LeadStage::BOOK_KS, 2];
        yield 'bws' => [IntroMeetingType::BWS, 0, null, LeadStage::BOOK_KS, 0];
        yield 'hom A' => [IntroMeetingType::HOM, 0, HomRating::A, LeadStage::INTRO_ATTENDED, 0];
        yield 'hom B' => [IntroMeetingType::HOM, 0, HomRating::B, LeadStage::INTRO_ATTENDED, 0];
        yield 'hom C' => [IntroMeetingType::HOM, 0, HomRating::C, LeadStage::BOOK_RI, 0];
        yield 'hom D' => [IntroMeetingType::HOM, 0, HomRating::D, LeadStage::BOOK_RI, 0];
        yield 'hom without rating' => [IntroMeetingType::HOM, 0, null, LeadStage::BOOK_KS, 0];
    }

    #[DataProvider('attendance')]
    public function testRecordAttendance(
        IntroMeetingType $type,
        int $sparksUsed,
        ?HomRating $rating,
        LeadStage $expectedStage,
        int $expectedSparksUsed,
    ): void {
        $lead = $this->lead($sparksUsed);

        $stage = $this->service()->recordAttendance($lead, $type, $rating);

        self::assertSame($expectedStage, $stage);
        self::assertSame($expectedSparksUsed, (int) $lead->get('cSparksUsed'));
        self::assertSame($type === IntroMeetingType::SPARK ? ['Lead:lead1'] : [], $this->savedEntities());
    }

    public function testThirdSparkAttendanceThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->recordAttendance($this->lead(2), IntroMeetingType::SPARK);
    }

    public function testGetIntroMeetingType(): void
    {
        $service = $this->service();

        self::assertSame(IntroMeetingType::SPARK, $service->getIntroMeetingType($this->lead(0, 'spark')));
        self::assertSame(IntroMeetingType::HOM, $service->getIntroMeetingType($this->lead(0, 'iom')));
        self::assertNull($service->getIntroMeetingType($this->lead(0, 'kickstart')));
        self::assertNull($service->getIntroMeetingType($this->lead(0, null)));
    }

    public function testShouldOfferAnotherIntro(): void
    {
        $service = $this->service();

        self::assertTrue($service->shouldOfferAnotherIntro($this->lead(1, 'spark')));
        self::assertFalse($service->shouldOfferAnotherIntro($this->lead(2, 'spark')));
        self::assertTrue($service->shouldOfferAnotherIntro($this->lead(0, 'bws')));
        self::assertFalse($service->shouldOfferAnotherIntro($this->lead(0, 'kickstart')));
        self::assertFalse($service->shouldOfferAnotherIntro($this->lead(0, null)));
    }

    private function lead(int $sparksUsed, ?string $meetingType = 'spark'): Entity
    {
        return FakeEntity::create('Lead', ['id' => 'lead1', 'cSparksUsed' => $sparksUsed, 'cMeetingType' => $meetingType]);
    }

    private function service(): IntroMeetingService
    {
        return new IntroMeetingService($this->inMemoryEntityManager());
    }
}
