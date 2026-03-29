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
     * @param string[] $extraGroupNames Optional explicit group names to add.
     */
    public function syncGroupsFromFields(
        Entity $destinationEntity,
        array $fieldsToWatch,
        ?Entity $sourceEntity = null,
        array $extraGroupNames = []
    ): void
    {
        $sourceEntity = $sourceEntity ?? $destinationEntity;
        
        $requiredGroupIds = $this->getGroupsFromFields($sourceEntity, $fieldsToWatch);
        $requiredGroupIds = array_merge($requiredGroupIds, $this->getGroupsFromNames($extraGroupNames));
        $this->setGroupsForEntity($destinationEntity, $requiredGroupIds);
    }

    /**
     * Sets assigned user from linked coach team, else from slim-fit center (same source as group fields).
     */
    public function syncAssignedUserFromTeamFields(Entity $entity, ?Entity $sourceEntity = null): void
    {
        $sourceEntity = $sourceEntity ?? $entity;

        $cTeam = $this->entityManager->getRelation($sourceEntity, 'cTeam')->findOne();
        if ($cTeam && $cTeam->get('assignedUserId')) {
            $entity->set('assignedUserId', $cTeam->get('assignedUserId'));
        }
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
        $entity->setMultiple(['teamsIds' => array_values(array_unique($requiredGroupIds))]);
    }

    private function getGroupsFromNames(array $groupNames): array
    {
        $requiredGroupIds = [];

        foreach ($groupNames as $groupName) {
            if (!is_string($groupName) || trim($groupName) === '') {
                continue;
            }

            $group = $this->getGroupByName(trim($groupName));
            if ($group) {
                $requiredGroupIds[] = $group->getId();
            }
        }

        return $requiredGroupIds;
    }

    private function getGroupByName(string $groupName): ?Entity
    {
        return $this->entityManager->getRDBRepository(Team::ENTITY_TYPE)->where(['name' => $groupName])->findOne();
    }
}