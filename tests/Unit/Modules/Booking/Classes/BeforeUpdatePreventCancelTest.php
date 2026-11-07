<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Classes;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\UpdateParams;
use Espo\Modules\Booking\Classes\RecordHooks\Meeting\BeforeUpdatePreventCancel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;

final class BeforeUpdatePreventCancelTest extends TestCase
{
    public function testCancellingThroughAnEditIsBlocked(): void
    {
        $meeting = FakeEntity::fetched('Meeting', ['id' => 'm1', 'status' => 'Planned'], ['status' => 'Cancelled']);

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage("Gebruik de knop 'Annuleer & Verwijder' om een afspraak te annuleren.");

        (new BeforeUpdatePreventCancel())->process($meeting, new UpdateParams());
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function allowedEdits(): iterable
    {
        yield 'held' => ['Planned', ['status' => 'Held']];
        yield 'not held' => ['Planned', ['status' => 'Not Held']];
        yield 'other field of cancelled meeting' => ['Cancelled', ['description' => 'x']];
        yield 'nothing changed' => ['Planned', []];
    }

    /**
     * @param array<string, string> $changes
     */
    #[DataProvider('allowedEdits')]
    public function testOtherEditsAreAllowed(string $status, array $changes): void
    {
        $this->expectNotToPerformAssertions();

        (new BeforeUpdatePreventCancel())->process(
            FakeEntity::fetched('Meeting', ['id' => 'm1', 'status' => $status, 'description' => ''], $changes),
            new UpdateParams(),
        );
    }
}
