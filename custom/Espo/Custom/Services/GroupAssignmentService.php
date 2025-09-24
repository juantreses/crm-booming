<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\Log;

class GroupAssignmentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Log $log,
    ) {}

    /**
     * Synchronizes an entity's assigned groups based on a provided list of fields.
     *
     * @param Entity $entity The entity to process (e.g., a Lead or Contact).
     * @param string[] $fieldsToWatch An array of field names on the entity to check.
     */
    public function syncGroupsFromFields(Entity $entity, array $fieldsToWatch): void
    {
        $entityType = $entity->getEntityType();
        $entityId = $entity->getId();
        
        $this->log->info("Processing group assignments for {$entityType} ID: {$entityId}");

        $requiredGroupIds = [];
        foreach ($fieldsToWatch as $field) {
            $relatedEntity = $entity->get($field);
            if ($relatedEntity && $relatedEntity->get('name')) {
                $group = $this->getGroupByName($relatedEntity->get('name'));
                if ($group) {
                    $requiredGroupIds[] = $group->getId();
                }
            }
        }

        $requiredGroupIds = array_unique($requiredGroupIds);

        $entity->setLinkMultipleIdList('teams', $requiredGroupIds);
        $this->log->info("Setting group assignments [" . implode(', ', $requiredGroupIds) . "] for {$entityType} ID: {$entityId}");
    }

    private function getGroupByName(string $groupName): ?Entity
    {
        return $this->entityManager->getRepository('Team')->where(['name' => $groupName])->findOne();
    }
}