<?php

declare(strict_types=1);

namespace Espo\Modules\Vanko\Services\Util;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;
use Espo\Core\ORM\Repository\Option\SaveOption;

/**
 * A generic helper service to find or create entities by name.
 */
class EntityFactory
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
    ) {}

    /**
     * Finds an entity by its name, or creates it if it does not exist.
     *
     * @param string $entityType The type of the entity (e.g., 'CTeam').
     * @param string $name The value of the 'name' field to search for.
     * @param array<string, mixed> $additionalData Data to set on the entity if it's being created.
     * @return Entity|null The found or newly created entity, or null on failure.
     */
    public function findOrCreate(string $entityType, string $name, array $additionalData = []): ?Entity
    {
        $this->log->info("Processing {$entityType}: {$name}");
        try {
            $entity = $this->entityManager
                ->getRepository($entityType)
                ->where(['name' => $name])
                ->findOne();

            if ($entity) {
                return $entity;
            }

            return $this->create($entityType, $name, $additionalData);
        } catch (\Exception $e) {
            $this->log->error("Failed to find or create {$entityType} '{$name}': " . $e->getMessage());
            return null;
        }
    }

    private function create(string $entityType, string $name, array $additionalData): ?Entity
    {
        $this->log->info("Creating new {$entityType}: {$name}");
        try {
            $entity = $this->entityManager->getEntity($entityType);
            $entity->set('name', $name);

            if (!empty($additionalData)) {
                $entity->set($additionalData);
            }

            $this->entityManager->saveEntity($entity);
            $this->log->info("Created {$entityType} {$entity->getId()} for: {$name}");

            return $entity;
        } catch (\Exception $e) {
            $this->log->error("Failed to create {$entityType} '{$name}': " . $e->getMessage());
            return null;
        }
    }
}