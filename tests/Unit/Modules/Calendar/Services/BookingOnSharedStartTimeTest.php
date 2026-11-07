<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Calendar\Services;

use Espo\Core\Exceptions\Conflict;
use Espo\Modules\Booking\Services\BookingService;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Calendar\Services\CalendarService;
use Espo\Modules\Calendar\Services\PublicCalendarAccess;
use Espo\Modules\LeadManager\Services\LeadService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\EntityCollection;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

/**
 * The booking widget shows the slots of one location (?location=brugge) and books with the
 * availabilityId of the chosen slot. BookingService checks that slot again, but asks the calendar
 * for all slots of the day without the location; CalendarService then keeps only one slot per
 * start time, so a slot of another location at the same time can take its place.
 */
final class BookingOnSharedStartTimeTest extends TestCase
{
    use InMemoryEntityManager;

    public function testWidgetShowsTheBruggeSlot(): void
    {
        $slots = $this->calendarService()->getAvailableSlots('kickstart', '2030-01-15', 'brugge');

        self::assertSame(['19:00 av-brugge'], array_map(static fn (array $s) => "{$s['start']} {$s['availabilityId']}", $slots));
    }

    public function testButBookingThatSlotFailsBecauseGentHasOneAtTheSameTime(): void
    {
        $calendar = FakeEntity::create('CCalendar', ['id' => 'cal1', 'type' => 'kickstart']);
        $access = $this->createStub(PublicCalendarAccess::class);
        $access->method('resolveAccessible')->willReturn($calendar);

        $bookingService = new BookingService(
            $this->inMemoryEntityManager(),
            $this->calendarService(),
            $this->createStub(CalendarRepository::class),
            $this->createStub(LeadService::class),
            $this->createStub(SlugService::class),
            $access,
        );

        $this->expectException(Conflict::class);
        $this->expectExceptionMessage('Helaas, tijdstip is niet (meer) beschikbaar.');

        $bookingService->processBooking([
            'calendarId' => 'kickstart',
            'availabilityId' => 'av-brugge',
            'date' => '2030-01-15',
            'time' => '19:00',
            'email' => 'jan@test.be',
            'note' => '',
        ]);
    }

    private function calendarService(): CalendarService
    {
        $calendar = FakeEntity::create('CCalendar', [
            'id' => 'cal1', 'isActive' => true, 'duration' => 60, 'bufferTime' => 0, 'seatsPerMeeting' => 4, 'minLeadTime' => 0,
        ]);
        $gent = FakeEntity::create('CAvailability', ['id' => 'av-gent', 'startTime' => '19:00', 'endTime' => '20:00', 'locationId' => 'loc-gent']);
        $brugge = FakeEntity::create('CAvailability', ['id' => 'av-brugge', 'startTime' => '19:00', 'endTime' => '20:00', 'locationId' => 'loc-brugge']);

        $repository = $this->createStub(CalendarRepository::class);
        $repository->method('findCalendarById')->willReturn($calendar);
        $repository->method('getAvailabilitiesForDate')->willReturnCallback(
            static fn (string $calendarId, string $date, ?string $locationId = null) => new EntityCollection(match ($locationId) {
                'loc-brugge' => [$brugge],
                'loc-gent' => [$gent],
                default => [$gent, $brugge],
            }),
        );
        $repository->method('getLocationsByIds')->willReturn([]);
        $repository->method('getBlockedByCalendarIds')->willReturn([]);
        $repository->method('getBlockingCalendarAvailabilities')->willReturn([]);
        $repository->method('getBlockingCalendarMeetings')->willReturn([]);
        $repository->method('getBookingsForDate')->willReturn([]);

        $slugService = $this->createStub(SlugService::class);
        $slugService->method('resolve')->willReturnCallback(static fn (string $type, ?string $identifier) => match ($identifier) {
            'kickstart', 'cal1' => 'cal1',
            'brugge' => 'loc-brugge',
            default => null,
        });

        return new CalendarService($this->inMemoryEntityManager(), $repository, $slugService);
    }
}
