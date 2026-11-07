<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ContactManager\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\ContactManager\Services\SavingsCardService;
use Espo\ORM\Entity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use Tests\Support\RecordingQueries;

final class SavingsCardServiceTest extends TestCase
{
    use InMemoryEntityManager;
    use RecordingQueries;

    /**
     * @return iterable<string, array{?float, int}>
     */
    public static function amounts(): iterable
    {
        yield 'no amount' => [null, 0];
        yield 'negative' => [-50.0, 0];
        yield 'below 10' => [9.99, 0];
        yield 'exactly 10' => [10.0, 1];
        yield 'float that is just below 30' => [29.999999999, 3];
        yield '39.99' => [39.99, 3];
        yield '125.50' => [125.5, 12];
    }

    #[DataProvider('amounts')]
    public function testOnePointPerTenEuro(?float $amount, int $points): void
    {
        self::assertSame($points, $this->service()->calculatePoints($amount));
    }

    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function clientTypes(): iterable
    {
        yield 'regular client' => ['klant', true];
        yield 'no type' => [null, true];
        yield 'pcx 15' => ['pcx_15', false];
        yield 'pcx 25' => ['pcx_25', false];
        yield 'pcx 35' => ['pcx_35', false];
        yield 'distributor' => ['distributor', false];
        yield 'distributeur' => ['distributeur', false];
    }

    #[DataProvider('clientTypes')]
    public function testOrderHistory(?string $type, bool $hasHistory): void
    {
        self::assertSame($hasHistory, $this->service()->hasOrderHistory($this->contact(['cTypeKlant' => $type])));
    }

    public function testCardValidUntilAndIncludingItsLastDay(): void
    {
        $service = $this->service();
        $contact = $this->contact(['cSavingsCardEnd' => '2026-06-30']);

        self::assertTrue($service->isCardValid($contact, new DateTimeImmutable('2026-06-30')));
        self::assertFalse($service->isCardValid($contact, new DateTimeImmutable('2026-07-01')));
        self::assertFalse($service->isCardValid($this->contact(['cSavingsCardEnd' => null]), new DateTimeImmutable('2026-01-01')));
    }

    public function testCanBookHomFromFortyValidPoints(): void
    {
        $service = $this->service();
        $validEnd = (new DateTimeImmutable('today +1 month'))->format('Y-m-d');

        self::assertTrue($service->canBookHom($this->contact(['cSavingsCardEnd' => $validEnd, 'cSavingsPointsBalance' => 40])));
        self::assertFalse($service->canBookHom($this->contact(['cSavingsCardEnd' => $validEnd, 'cSavingsPointsBalance' => 39])));
        self::assertFalse($service->canBookHom($this->contact(['cSavingsCardEnd' => '2020-01-01', 'cSavingsPointsBalance' => 80])));
        self::assertFalse($service->canBookHom($this->contact([
            'cSavingsCardEnd' => $validEnd, 'cSavingsPointsBalance' => 80, 'cTypeKlant' => 'pcx_25',
        ])));
    }

    public function testCardStartsAtTheFirstOrderAndLastsOneYear(): void
    {
        $contact = $this->contact();
        $this->orders(['2026-01-15' => 12, '2026-03-01' => 20, '2026-10-01' => 10]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2026-11-01'));

        self::assertSame('2026-01-15', $contact->get('cSavingsCardStart'));
        self::assertSame('2027-01-14', $contact->get('cSavingsCardEnd'));
        self::assertSame(42, $contact->get('cSavingsPointsBalance'));
        self::assertSame(['Contact:c1'], $this->savedEntities());
        self::assertSame(['COrder where {"contactId":"c1"} order dateOrdered ASC find'], $this->queries);
    }

    public function testOrderAfterTheCardEndStartsANewCardAtZero(): void
    {
        $contact = $this->contact(['cSavingsCardStart' => '2025-01-15', 'cSavingsPointsRedeemed' => 40]);
        $this->orders(['2025-01-15' => 50, '2026-01-14' => 5, '2026-01-15' => 7, '2026-02-01' => 3]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2026-03-01'));

        // 2026-01-14 is the last day of the first card; 2026-01-15 starts a new one.
        self::assertSame('2026-01-15', $contact->get('cSavingsCardStart'));
        self::assertSame('2027-01-14', $contact->get('cSavingsCardEnd'));
        self::assertSame(10, $contact->get('cSavingsPointsBalance'));
        // Redemptions of the old card no longer count.
        self::assertSame(0, $contact->get('cSavingsPointsRedeemed'));
    }

    public function testRedeemedPointsAreSubtractedFromTheSameCard(): void
    {
        $contact = $this->contact(['cSavingsCardStart' => '2026-01-15', 'cSavingsPointsRedeemed' => 40]);
        $this->orders(['2026-01-15' => 30, '2026-02-01' => 25]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2026-03-01'));

        self::assertSame(15, $contact->get('cSavingsPointsBalance'));
        self::assertSame(40, $contact->get('cSavingsPointsRedeemed'));
    }

    public function testBalanceNeverGoesBelowZero(): void
    {
        $contact = $this->contact(['cSavingsCardStart' => '2026-01-15', 'cSavingsPointsRedeemed' => 40]);
        $this->orders(['2026-01-15' => 30]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2026-03-01'));

        self::assertSame(0, $contact->get('cSavingsPointsBalance'));
    }

    public function testExpiredCardHasNoBalance(): void
    {
        $contact = $this->contact(['cSavingsCardStart' => '2025-01-15']);
        $this->orders(['2025-01-15' => 50]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2026-01-15'));

        self::assertSame('2026-01-14', $contact->get('cSavingsCardEnd'));
        self::assertSame(0, $contact->get('cSavingsPointsBalance'));
    }

    public function testPcxAndDistributorsHaveNoBalance(): void
    {
        $contact = $this->contact(['cTypeKlant' => 'distributor']);
        $this->orders(['2026-01-15' => 50]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2026-02-01'));

        self::assertSame('2026-01-15', $contact->get('cSavingsCardStart'));
        self::assertSame(0, $contact->get('cSavingsPointsBalance'));
    }

    public function testWithoutOrdersThereIsNoCard(): void
    {
        $contact = $this->contact(['cSavingsCardStart' => '2025-01-15', 'cSavingsCardEnd' => '2026-01-14', 'cSavingsPointsBalance' => 20]);
        $this->orders(['' => 99]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2025-06-01'));

        self::assertNull($contact->get('cSavingsCardStart'));
        self::assertNull($contact->get('cSavingsCardEnd'));
        self::assertSame(0, $contact->get('cSavingsPointsBalance'));
    }

    public function testCardOfALeapDay(): void
    {
        $contact = $this->contact();
        $this->orders(['2028-02-29' => 10]);

        $this->service()->recalculateBalance($contact, new DateTimeImmutable('2028-03-01'));

        self::assertSame('2029-02-28', $contact->get('cSavingsCardEnd'));
    }

    public function testRecalculateById(): void
    {
        $contact = $this->addRecord($this->contact());
        $this->orders(['2026-01-15' => 12]);

        $this->service()->recalculateBalance('c1', new DateTimeImmutable('2026-02-01'));
        $this->service()->recalculateBalance('gone', new DateTimeImmutable('2026-02-01'));

        self::assertSame(12, $contact->get('cSavingsPointsBalance'));
        self::assertCount(1, $this->queries);
    }

    public function testRedeemHomPoints(): void
    {
        $today = new DateTimeImmutable('today');
        $contact = $this->contact([
            'cSavingsCardStart' => $today->format('Y-m-d'),
            'cSavingsCardEnd' => $today->modify('+1 year -1 day')->format('Y-m-d'),
            'cSavingsPointsBalance' => 45,
            'cSavingsPointsRedeemed' => 0,
        ]);
        $this->orders([$today->format('Y-m-d') => 45]);

        $this->service()->redeemHomPoints($contact);

        self::assertSame(40, $contact->get('cSavingsPointsRedeemed'));
        self::assertSame(5, $contact->get('cSavingsPointsBalance'));
        self::assertSame(['Contact:c1', 'Contact:c1'], $this->savedEntities());
    }

    public function testRedeemNeedsFortyValidPoints(): void
    {
        $tooFew = $this->contact(['cSavingsCardEnd' => (new DateTimeImmutable('today +1 month'))->format('Y-m-d'), 'cSavingsPointsBalance' => 39]);
        $expired = $this->contact(['cSavingsCardEnd' => '2020-01-01', 'cSavingsPointsBalance' => 80]);

        foreach ([$tooFew, $expired] as $contact) {
            try {
                $this->service()->redeemHomPoints($contact);
                self::fail('Expected BadRequest');
            } catch (BadRequest $e) {
                self::assertSame('Onvoldoende geldige spaarkaartpunten (minimum 40).', $e->getMessage());
            }
        }

        self::assertSame([], $this->savedEntities());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function contact(array $values = []): Entity
    {
        return FakeEntity::create('Contact', [
            'id' => 'c1',
            'cTypeKlant' => 'klant',
            'cSavingsCardStart' => null,
            'cSavingsCardEnd' => null,
            'cSavingsPointsBalance' => 0,
            'cSavingsPointsRedeemed' => 0,
            ...$values,
        ]);
    }

    /**
     * @param array<string, int> $pointsPerDate order date ('' for none) => savings points
     */
    private function orders(array $pointsPerDate): void
    {
        $orders = [];
        foreach ($pointsPerDate as $date => $points) {
            $orders[] = FakeEntity::create('COrder', ['id' => 'o' . count($orders), 'dateOrdered' => $date ?: null, 'savingsPoints' => $points]);
        }

        $this->queryResults['COrder'] = $orders;
    }

    private function service(): SavingsCardService
    {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->recordingRepository($type));

        return new SavingsCardService($entityManager);
    }
}
