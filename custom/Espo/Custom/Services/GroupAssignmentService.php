<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Entities\Team;

class GroupAssignmentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Synchronizes an entity's assigned groups based on a provided list of fields.
     *
     * @param Entity $destinationEntity The entity to assign groups to.
     * @param string[] $fieldsToWatch An array of field names to check for groups.
     * @param Entity|null $sourceEntity The entity to read group names from. If null, the destinationEntity is used.
     */
    public function syncGroupsFromFields(Entity $destinationEntity, array $fieldsToWatch, ?Entity $sourceEntity = null): void
    {
        $sourceEntity = $sourceEntity ?? $destinationEntity;
        
        $requiredGroupIds = $this->getGroupsFromFields($sourceEntity, $fieldsToWatch);
        $this->setGroupsForEntity($destinationEntity, $requiredGroupIds);
    }

    private function getGroupsFromFields(Entity $entity, array $fieldsToWatch): array
    {
        $requiredGroupIds = [];
        foreach ($fieldsToWatch as $field) {
            $relatedEntity = $this->entityManager->getRelation($entity, $field)->findOne();
            if ($relatedEntity && $relatedEntity->get('name')) {
                $group = $this->getGroupByName($relatedEntity->get('name'));
                if ($group) {
                    $requiredGroupIds[] = $group->getId();
                }
            }
        }

        return array_unique($requiredGroupIds);
    }

    private function setGroupsForEntity(Entity $entity, array $requiredGroupIds): void
    {
        $entity->setMultiple(['teamsIds' => $requiredGroupIds]);
    }

    private function getGroupByName(string $groupName): ?Entity
    {
        return $this->entityManager->getRDBRepository(Team::ENTITY_TYPE)->where(['name' => $groupName])->findOne();
    }
}