<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ContactManager\Jobs;

use Espo\Modules\ContactManager\Jobs\ExpireSavingsCards;
use Espo\Modules\ContactManager\Services\SavingsCardService;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use Tests\Support\RecordingQueries;

final class ExpireSavingsCardsTest extends TestCase
{
    use InMemoryEntityManager;
    use RecordingQueries;

    public function testRecalculatesContactsWithAnExpiredCardAndABalance(): void
    {
        $this->queryResults['Contact'] = [
            FakeEntity::create('Contact', ['id' => 'c1']),
            FakeEntity::create('Contact', ['id' => 'c2']),
        ];
        $recalculated = [];
        $service = $this->createStub(SavingsCardService::class);
        $service->method('recalculateBalance')->willReturnCallback(function (Entity|string $contact) use (&$recalculated): void {
            $recalculated[] = $contact;
        });

        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->recordingRepository($type));

        (new ExpireSavingsCards($entityManager, $service))->run();

        self::assertSame(['c1', 'c2'], $recalculated);
        self::assertSame(
            ['Contact where {"cSavingsCardEnd<":"' . date('Y-m-d') . '","cSavingsPointsBalance>":0} find'],
            $this->queries,
        );
    }
}
