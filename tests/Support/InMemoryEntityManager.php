<?php

declare(strict_types=1);

namespace Tests\Support;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Builds an EntityManager stub backed by a list of FakeEntity records.
 *
 * getEntityById() looks up the records, saveEntity() records which entities were saved
 * (read them with savedEntities()), getNewEntity() hands out new FakeEntity instances with ids new1, new2, ...
 *
 * @mixin TestCase
 */
trait InMemoryEntityManager
{
    /** @var array<string, array<string, Entity>> */
    protected array $records = [];

    /** @var list<Entity> */
    protected array $saved = [];

    /** @var list<Entity> */
    protected array $created = [];

    protected function addRecord(Entity $entity): Entity
    {
        $this->records[$entity->getEntityType()][$entity->getId()] = $entity;

        return $entity;
    }

    protected function inMemoryEntityManager(): EntityManager&Stub
    {
        $entityManager = $this->createStub(EntityManager::class);

        $entityManager->method('getEntityById')->willReturnCallback(
            fn (string $type, string $id) => $this->records[$type][$id] ?? null,
        );
        $entityManager->method('saveEntity')->willReturnCallback(
            function (Entity $entity): void {
                $this->saved[] = $entity;
            },
        );
        $entityManager->method('getNewEntity')->willReturnCallback(
            function (string $type): Entity {
                $entity = $this->newEntity($type, 'new' . (count($this->created) + 1));
                $this->created[] = $entity;

                return $entity;
            },
        );

        return $entityManager;
    }

    /**
     * Override to hand out a specific entity class from getNewEntity().
     */
    protected function newEntity(string $entityType, string $id): Entity
    {
        return FakeEntity::create($entityType, ['id' => $id]);
    }

    /**
     * @return list<string> "EntityType:id" per save, in order.
     */
    protected function savedEntities(): array
    {
        return array_map(static fn (Entity $e) => $e->getEntityType() . ':' . $e->getId(), $this->saved);
    }
}
