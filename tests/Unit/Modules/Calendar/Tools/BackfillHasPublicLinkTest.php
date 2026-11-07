<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Calendar\Tools;

use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Modules\Calendar\Tools\BackfillHasPublicLink;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;
use Tests\Support\RecordingQueries;

final class BackfillHasPublicLinkTest extends TestCase
{
    use InMemoryEntityManager;
    use RecordingQueries;

    public function testCopiesIsPubliekToHasPublicLink(): void
    {
        $this->queryResults['CCalendar'] = [
            FakeEntity::create('CCalendar', ['id' => 'c1', 'isPubliek' => true, 'hasPublicLink' => null]),
            FakeEntity::create('CCalendar', ['id' => 'c2', 'isPubliek' => null, 'hasPublicLink' => true]),
        ];
        $saveOptions = [];
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->recordingRepository($type));
        // Runs next to the trait's own saveEntity callback, which records the saved entities.
        $entityManager->method('saveEntity')->willReturnCallback(function (Entity $entity, array $options = []) use (&$saveOptions): void {
            $saveOptions[] = $options;
        });

        $io = $this->createMock(IO::class);
        $io->expects(self::once())->method('writeLine')->with('Backfilled hasPublicLink for 2 calendar(s).');

        (new BackfillHasPublicLink($entityManager))->run(new Params([], [], []), $io);

        self::assertSame([true, false], array_map(static fn (Entity $c) => $c->get('hasPublicLink'), $this->queryResults['CCalendar']));
        self::assertSame(['CCalendar:c1', 'CCalendar:c2'], $this->savedEntities());
        self::assertSame([['silent' => true], ['silent' => true]], $saveOptions);
        self::assertSame(['CCalendar where {"deleted":false} find'], $this->queries);
    }
}
