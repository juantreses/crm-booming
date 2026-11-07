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
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

/**
 * The booking widget shows the slots of one location or variant (?location=brugge&variant=avond) and books
 * with the availabilityId of the chosen slot. BookingService checks that slot again with the real
 * CalendarService, so other locations or variants with a slot at the same time must not get in the way.
 */
final class BookingOnSharedStartTimeTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var list<array{?string, ?string}> location and variant per availability lookup */
    private array $lookups = [];

    /** @var list<Entity> */
    private array $availabilities;

    protected function setUp(): void
    {
        $this->availabilities = [
            $this->addRecord(FakeEntity::create('CAvailability', ['id' => 'av-gent', 'startTime' => '19:00', 'endTime' => '20:00', 'locationId' => 'loc-gent', 'publicSlug' => null])),
            $this->addRecord(FakeEntity::create('CAvailability', ['id' => 'av-brugge', 'startTime' => '19:00', 'endTime' => '20:00', 'locationId' => 'loc-brugge', 'publicSlug' => null])),
            $this->addRecord(FakeEntity::create('CAvailability', ['id' => 'av-brugge-avond', 'startTime' => '19:00', 'endTime' => '20:00', 'locationId' => 'loc-brugge', 'publicSlug' => 'avond'])),
        ];
    }

    public function testWidgetShowsTheBruggeSlot(): void
    {
        $slots = $this->calendarService()->getAvailableSlots('kickstart', '2030-01-15', 'brugge');

        self::assertSame(['19:00 av-brugge', '19:00 av-brugge-avond'], array_map(static fn (array $s) => "{$s['start']} {$s['availabilityId']}", $slots));
    }

    public function testBookingTheBruggeSlotIsCheckedAtBrugge(): void
    {
        $this->bookingService()->processBooking($this->booking('av-brugge'));

        self::assertSame([['loc-brugge', null]], $this->lookups);
        self::assertSame('av-brugge', $this->created[0]->get('cAvailabilityId'));
        self::assertSame('loc-brugge', $this->created[0]->get('cLocationId'));
    }

    public function testBookingAVariantIsCheckedForThatVariant(): void
    {
        $this->bookingService()->processBooking($this->booking('av-brugge-avond'));

        self::assertSame([['loc-brugge', 'avond']], $this->lookups);
        self::assertSame('av-brugge-avond', $this->created[0]->get('cAvailabilityId'));
    }

    public function testUnknownAvailabilityIsAConflict(): void
    {
        $this->expectException(Conflict::class);

        try {
            $this->bookingService()->processBooking($this->booking('av-weg'));
        } finally {
            self::assertSame([[null, null]], $this->lookups);
        }
    }

    /**
     * @return array<string, string>
     */
    private function booking(string $availabilityId): array
    {
        return [
            'calendarId' => 'kickstart',
            'availabilityId' => $availabilityId,
            'date' => '2030-01-15',
            'time' => '19:00',
            'email' => 'jan@test.be',
            'note' => '',
        ];
    }

    private function bookingService(): BookingService
    {
        $calendar = FakeEntity::create('CCalendar', ['id' => 'cal1', 'type' => 'kickstart']);
        $access = $this->createStub(PublicCalendarAccess::class);
        $access->method('resolveAccessible')->willReturn($calendar);

        $leadService = $this->createStub(LeadService::class);
        $leadService->method('findOrCreate')->willReturn(FakeEntity::create('Lead', ['id' => 'lead1', 'name' => 'Jan']));

        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(function (): RDBRepository {
            $repository = $this->createStub(RDBRepository::class);
            $repository->method('getRelation')->willReturn($this->createStub(RDBRelation::class));

            return $repository;
        });

        return new BookingService(
            $entityManager,
            $this->calendarService(),
            $this->createStub(CalendarRepository::class),
            $leadService,
            $this->createStub(SlugService::class),
            $access,
        );
    }

    private function calendarService(): CalendarService
    {
        $calendar = FakeEntity::create('CCalendar', [
            'id' => 'cal1', 'isActive' => true, 'duration' => 60, 'bufferTime' => 0, 'seatsPerMeeting' => 4, 'minLeadTime' => 0,
        ]);

        $repository = $this->createStub(CalendarRepository::class);
        $repository->method('findCalendarById')->willReturn($calendar);
        $repository->method('getAvailabilitiesForDate')->willReturnCallback(
            function (string $calendarId, string $date, ?string $locationId = null, ?string $teamId = null, ?string $variant = null): EntityCollection {
                $this->lookups[] = [$locationId, $variant];

                return new EntityCollection(array_values(array_filter(
                    $this->availabilities,
                    static fn (Entity $a) => (!$locationId || $a->get('locationId') === $locationId)
                        && (!$variant || $a->get('publicSlug') === $variant),
                )));
            },
        );
        $repository->method('getLocationsByIds')->willReturnCallback(static fn (array $ids) => array_combine($ids, array_map(
            static fn (string $id) => [
                'id' => $id, 'name' => $id, 'addressStreet' => null, 'addressCity' => null,
                'addressState' => null, 'addressCountry' => null, 'addressPostalCode' => null,
            ],
            $ids,
        )));
        $repository->method('getBlockedByCalendarIds')->willReturn([]);
        $repository->method('getBlockingCalendarAvailabilities')->willReturn([]);
        $repository->method('getBlockingCalendarMeetings')->willReturn([]);
        $repository->method('getBookingsForDate')->willReturn([]);

        $slugService = $this->createStub(SlugService::class);
        $slugService->method('resolve')->willReturnCallback(static fn (string $type, ?string $identifier) => match ($identifier) {
            'kickstart', 'cal1' => 'cal1',
            'brugge', 'loc-brugge' => 'loc-brugge',
            'loc-gent' => 'loc-gent',
            default => null,
        });

        return new CalendarService($this->inMemoryEntityManager(), $repository, $slugService);
    }
}
