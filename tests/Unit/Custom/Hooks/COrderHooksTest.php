<?php

declare(strict_types=1);

namespace Tests\Unit\Custom\Hooks;

use Espo\Custom\Hooks\COrder\AfterRemoveHook;
use Espo\Custom\Hooks\COrder\AfterSaveHook;
use Espo\Custom\Hooks\COrder\BeforeSaveHook;
use Espo\Modules\ContactManager\Services\SavingsCardService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class COrderHooksTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var list<string> */
    private array $recalculated = [];

    protected function setUp(): void
    {
        $this->addRecord(FakeEntity::create('Contact', ['id' => 'client', 'cTypeKlant' => 'klant']));
        $this->addRecord(FakeEntity::create('Contact', ['id' => 'pcx', 'cTypeKlant' => 'pcx_25']));
    }

    public function testProgramEndsThreeWeeksAfterTheOrder(): void
    {
        $order = $this->newOrder(['dateOrdered' => '2026-12-20']);

        $this->beforeSave($order);

        self::assertSame('2027-01-10', $order->get('programEnd'));
    }

    public function testNoProgramEndWithoutOrderDate(): void
    {
        $order = $this->newOrder(['dateOrdered' => null, 'programEnd' => '2026-01-01']);

        $this->beforeSave($order);

        self::assertSame('2026-01-01', $order->get('programEnd'));
    }

    public function testNewOrderEarnsPointsForAClientWithOrderHistory(): void
    {
        $client = $this->newOrder(['contactId' => 'client', 'amount' => 125.5]);
        $pcx = $this->newOrder(['contactId' => 'pcx', 'amount' => 125.5]);
        $noContact = $this->newOrder(['contactId' => null, 'amount' => 125.5]);

        foreach ([$client, $pcx, $noContact] as $order) {
            $this->beforeSave($order);
        }

        self::assertSame([12, 0, 0], [$client->get('savingsPoints'), $pcx->get('savingsPoints'), $noContact->get('savingsPoints')]);
    }

    public function testPointsOfAnExistingOrderAreFixed(): void
    {
        // Points stay as they were when the order was registered, e.g. after the client became PCX.
        $order = FakeEntity::fetched('COrder', ['id' => 'o1', 'contactId' => 'pcx', 'amount' => 100.0, 'savingsPoints' => 10, 'dateOrdered' => null], ['description' => 'x']);

        $this->beforeSave($order);

        self::assertSame(10, $order->get('savingsPoints'));
    }

    public function testPointsAreRecalculatedWhenAmountOrClientChanges(): void
    {
        $newAmount = FakeEntity::fetched('COrder', ['id' => 'o1', 'contactId' => 'client', 'amount' => 100.0, 'savingsPoints' => 10, 'dateOrdered' => null], ['amount' => 55.0]);
        $newClient = FakeEntity::fetched('COrder', ['id' => 'o2', 'contactId' => 'client', 'amount' => 100.0, 'savingsPoints' => 10, 'dateOrdered' => null], ['contactId' => 'pcx']);

        $this->beforeSave($newAmount);
        $this->beforeSave($newClient);

        self::assertSame(5, $newAmount->get('savingsPoints'));
        self::assertSame(0, $newClient->get('savingsPoints'));
    }

    public function testAfterSaveRecalculatesTheOldAndNewClient(): void
    {
        $hook = new AfterSaveHook($this->savingsCardService());

        $hook->afterSave(FakeEntity::fetched('COrder', ['id' => 'o1', 'contactId' => 'client'], ['contactId' => 'pcx']), SaveOptions::fromAssoc([]));
        self::assertSame(['pcx', 'client'], $this->recalculated);

        $this->recalculated = [];
        $hook->afterSave(FakeEntity::fetched('COrder', ['id' => 'o1', 'contactId' => 'client'], ['amount' => 10.0]), SaveOptions::fromAssoc([]));
        self::assertSame(['client'], $this->recalculated);

        $this->recalculated = [];
        $hook->afterSave($this->newOrder(['contactId' => null]), SaveOptions::fromAssoc([]));
        self::assertSame([], $this->recalculated);
    }

    public function testAfterRemoveRecalculatesTheClient(): void
    {
        $hook = new AfterRemoveHook($this->savingsCardService());

        $hook->afterRemove(FakeEntity::create('COrder', ['id' => 'o1', 'contactId' => 'client']), RemoveOptions::fromAssoc([]));
        $hook->afterRemove(FakeEntity::create('COrder', ['id' => 'o2', 'contactId' => null]), RemoveOptions::fromAssoc([]));

        self::assertSame(['client'], $this->recalculated);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function newOrder(array $values): Entity
    {
        return FakeEntity::create('COrder', ['id' => 'o1', 'contactId' => null, 'amount' => null, 'dateOrdered' => null, 'savingsPoints' => null, ...$values]);
    }

    private function beforeSave(Entity $order): void
    {
        $entityManager = $this->inMemoryEntityManager();

        (new BeforeSaveHook($entityManager, new SavingsCardService($entityManager)))->beforeSave($order, SaveOptions::fromAssoc([]));
    }

    private function savingsCardService(): SavingsCardService
    {
        $service = $this->createStub(SavingsCardService::class);
        $service->method('recalculateBalance')->willReturnCallback(function (Entity|string $contact): void {
            $this->recalculated[] = $contact;
        });

        return $service;
    }
}
