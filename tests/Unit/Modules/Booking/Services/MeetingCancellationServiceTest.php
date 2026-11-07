<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Services;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Booking\Services\MeetingCancellationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class MeetingCancellationServiceTest extends TestCase
{
    use InMemoryEntityManager;

    /**
     * @return iterable<string, array{string}>
     */
    public static function cancellableStatuses(): iterable
    {
        yield 'planned' => ['Planned'];
        yield 'tentative' => ['Tentative'];
    }

    #[DataProvider('cancellableStatuses')]
    public function testCancelsSavesAndRemoves(string $status): void
    {
        $meeting = $this->addRecord(FakeEntity::fetched('Meeting', ['id' => 'm1', 'status' => $status]));

        $result = $this->service()->cancelAndRemove('m1');

        self::assertSame(['success' => true, 'mailError' => null], $result);
        self::assertSame('Cancelled', $meeting->get('status'));
        self::assertSame(['Meeting:m1'], $this->savedEntities());
        self::assertSame([$meeting], $this->removed);
    }

    public function testReturnsMailErrorSetDuringSave(): void
    {
        $meeting = $this->addRecord(FakeEntity::fetched('Meeting', ['id' => 'm1', 'status' => 'Planned']));
        $entityManager = $this->inMemoryEntityManager();
        // The CancellationEmail hook only sets cMailError in memory during the save.
        $entityManager->method('saveEntity')->willReturnCallback(static function () use ($meeting): void {
            $meeting->set('cMailError', 'Annulering mail fout: SMTP down');
        });

        $result = (new MeetingCancellationService($entityManager, $this->acl()))->cancelAndRemove('m1');

        self::assertSame(['success' => true, 'mailError' => 'Annulering mail fout: SMTP down'], $result);
        self::assertSame([$meeting], $this->removed);
    }

    public function testUnknownMeeting(): void
    {
        $this->expectException(NotFound::class);
        $this->expectExceptionMessage('Afspraak niet gevonden.');

        $this->service()->cancelAndRemove('missing');
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function missingRights(): iterable
    {
        yield 'no edit' => [false, true];
        yield 'no delete' => [true, false];
    }

    #[DataProvider('missingRights')]
    public function testRequiresEditAndDeleteRights(bool $canEdit, bool $canDelete): void
    {
        $this->addRecord(FakeEntity::fetched('Meeting', ['id' => 'm1', 'status' => 'Planned']));

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('Geen rechten om deze afspraak te annuleren.');

        try {
            $this->service($canEdit, $canDelete)->cancelAndRemove('m1');
        } finally {
            self::assertSame([], $this->savedEntities());
            self::assertSame([], $this->removed);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherStatuses(): iterable
    {
        yield 'held' => ['Held'];
        yield 'not held' => ['Not Held'];
        yield 'already cancelled' => ['Cancelled'];
    }

    #[DataProvider('otherStatuses')]
    public function testOnlyPlannedMeetingsCanBeCancelled(string $status): void
    {
        $this->addRecord(FakeEntity::fetched('Meeting', ['id' => 'm1', 'status' => $status]));

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Enkel geplande afspraken kunnen geannuleerd worden.');

        $this->service()->cancelAndRemove('m1');
    }

    private function service(bool $canEdit = true, bool $canDelete = true): MeetingCancellationService
    {
        return new MeetingCancellationService($this->inMemoryEntityManager(), $this->acl($canEdit, $canDelete));
    }

    private function acl(bool $canEdit = true, bool $canDelete = true): Acl
    {
        $acl = $this->createStub(Acl::class);
        $acl->method('checkEntityEdit')->willReturn($canEdit);
        $acl->method('checkEntityDelete')->willReturn($canDelete);

        return $acl;
    }
}
