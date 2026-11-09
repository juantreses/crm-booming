<?php

declare(strict_types=1);

namespace Tests\Unit\Custom\Services;

use Espo\Custom\Services\EntitySyncService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\RDBRelation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class EntitySyncServiceTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var array<string, ?Entity> "entityId.link" => related entity */
    private array $relations = [];

    /** @var list<string> */
    private array $relationLookups = [];

    private const VALUES = [
        'firstName' => 'Jan',
        'lastName' => 'Peeters',
        'emailAddress' => 'jan@test.be',
        'phoneNumber' => '+32470123456',
        'cTeamId' => 'team1',
        'cSlimFitCenterId' => 'sfc1',
        'status' => 'Assigned',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function watchedFields(): iterable
    {
        foreach (['firstName', 'lastName', 'emailAddress', 'phoneNumber', 'cTeamId', 'cSlimFitCenterId'] as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('watchedFields')]
    public function testChangedFieldOfALeadIsCopiedToItsContact(string $field): void
    {
        $lead = FakeEntity::fetched('Lead', ['id' => 'lead1', ...self::VALUES], [$field => 'nieuw', 'status' => 'Dead']);
        $contactValues = array_map(static fn (string $value) => "eigen $value", self::VALUES);
        $contact = $this->relations['lead1.createdContact'] = FakeEntity::create('Contact', ['id' => 'c1', ...$contactValues]);

        $this->service()->syncFromLead($lead);

        self::assertSame('nieuw', $contact->get($field));
        // Only the changed watched field is copied; the contact keeps its own other values.
        foreach (array_diff(array_keys(self::VALUES), [$field]) as $other) {
            self::assertSame($contactValues[$other], $contact->get($other), $other);
        }
        self::assertSame(['Contact:c1'], $this->savedEntities());
    }

    public function testChangedFieldsOfAContactAreCopiedToItsOriginalLead(): void
    {
        $contact = FakeEntity::fetched('Contact', ['id' => 'c1', ...self::VALUES], ['lastName' => 'Janssens', 'cTeamId' => 'team2']);
        $lead = $this->relations['c1.originalLead'] = FakeEntity::create('Lead', ['id' => 'lead1', ...self::VALUES, 'firstName' => 'Johannes']);

        $this->service()->syncFromContact($contact);

        self::assertSame('Janssens', $lead->get('lastName'));
        self::assertSame('team2', $lead->get('cTeamId'));
        self::assertSame('Johannes', $lead->get('firstName'));
        self::assertSame(['Lead:lead1'], $this->savedEntities());
    }

    public function testOtherChangesAreNotSynced(): void
    {
        $lead = FakeEntity::fetched('Lead', ['id' => 'lead1', ...self::VALUES], ['status' => 'Dead']);
        $this->relations['lead1.createdContact'] = FakeEntity::create('Contact', ['id' => 'c1']);

        $this->service()->syncFromLead($lead);

        self::assertSame([], $this->savedEntities());
        self::assertSame([], $this->relationLookups);
    }

    public function testWithoutRelatedRecordNothingIsSaved(): void
    {
        $service = $this->service();

        $service->syncFromLead(FakeEntity::fetched('Lead', ['id' => 'lead1', ...self::VALUES], ['firstName' => 'Piet']));
        $service->syncFromContact(FakeEntity::fetched('Contact', ['id' => 'c1', ...self::VALUES], ['firstName' => 'Piet']));

        self::assertSame([], $this->savedEntities());
        self::assertSame(['lead1.createdContact', 'c1.originalLead'], $this->relationLookups);
    }

    private function service(): EntitySyncService
    {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRelation')->willReturnCallback(function (Entity $entity, string $link): RDBRelation {
            $key = "{$entity->getId()}.$link";
            $relation = $this->createStub(RDBRelation::class);
            $relation->method('findOne')->willReturnCallback(function () use ($key): ?Entity {
                $this->relationLookups[] = $key;

                return $this->relations[$key] ?? null;
            });

            return $relation;
        });

        return new EntitySyncService($entityManager);
    }
}
