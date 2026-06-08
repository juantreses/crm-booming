<?php

namespace Espo\Custom\Services;

use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Entities\Team;

class GroupAssignmentService
{
    private const CONTACT_TEAM_FIELDS = [
        'cSlimFitCenter',
        'cTeam',
    ];

    private const SPONSOR_CHAIN_MAX_LEVELS = 3;

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

        if (in_array('cTeam', $fieldsToWatch, true)) {
            $cTeam = $this->entityManager->getRelation($sourceEntity, 'cTeam')->findOne();
            $requiredGroupIds = array_merge(
                $requiredGroupIds,
                $this->getGroupsFromNames($this->getSponsorChainGroupNames($cTeam))
            );
        }

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

    /**
     * @return string[]
     */
    public function getCoachExtraGroupNamesFromContact(?Entity $contact): array
    {
        if (!$contact) {
            return [];
        }

        $coachGroupName = trim(sprintf(
            '%s %s',
            (string) $contact->get('firstName'),
            (string) $contact->get('lastName')
        ));

        return $coachGroupName !== '' ? [$coachGroupName] : [];
    }

    /**
     * Re-applies group assignment on leads/contacts for a team and its downlines.
     * Used when sponsor relationships change on a coach team.
     */
    public function syncLeadsAndContactsForTeamAndDownlines(Entity $team): void
    {
        $this->syncLeadsAndContactsForTeam($team);

        $downlines = $this->entityManager->getRelation($team, 'downlines')->find();
        foreach ($downlines as $downline) {
            $this->syncLeadsAndContactsForTeamAndDownlines($downline);
        }
    }

    /**
     * @return string[]
     */
    private function getSponsorChainGroupNames(?Entity $cTeam): array
    {
        if (!$cTeam) {
            return [];
        }

        $groupNames = [];
        $currentTeam = $cTeam;

        for ($level = 0; $level < self::SPONSOR_CHAIN_MAX_LEVELS; $level++) {
            $sponsor = $this->entityManager->getRelation($currentTeam, 'sponser')->findOne();
            if (!$sponsor) {
                break;
            }

            $name = $sponsor->get('name');
            if (!is_string($name) || trim($name) === '') {
                break;
            }

            $groupNames[] = trim($name);
            $currentTeam = $sponsor;
        }

        return $groupNames;
    }

    private function syncLeadsAndContactsForTeam(Entity $team): void
    {
        $teamId = $team->getId();
        if (!$teamId) {
            return;
        }

        foreach (['Lead', 'Contact'] as $entityType) {
            $entities = $this->entityManager
                ->getRDBRepository($entityType)
                ->where(['cTeamId' => $teamId])
                ->find();

            foreach ($entities as $entity) {
                $this->entityManager->saveEntity($entity, [SaveOption::SILENT => true]);
            }
        }
    }

    /**
     * Re-applies group assignment on existing bodyscans and notes when the linked contact's team changes.
     * Only persists records whose teams actually changed.
     */
    public function syncRelatedTeamsFromContact(Entity $contact): void
    {
        $coachExtraGroupNames = $this->getCoachExtraGroupNamesFromContact($contact);

        $this->propagateTeamsToRelation($contact, 'cBodyscans', $coachExtraGroupNames);
        $this->propagateTeamsToRelation($contact, 'cNotes', []);
    }

    /**
     * @param string[] $extraGroupNames
     */
    private function propagateTeamsToRelation(
        Entity $contact,
        string $relationName,
        array $extraGroupNames
    ): void
    {
        $relatedEntities = $this->entityManager
            ->getRelation($contact, $relationName)
            ->find();

        foreach ($relatedEntities as $entity) {
            if (!$this->applyGroupSyncFromContact($entity, $contact, $extraGroupNames)) {
                continue;
            }

            $this->entityManager->saveEntity($entity, [SaveOption::SILENT => true]);
        }
    }

    /**
     * @param string[] $extraGroupNames
     */
    private function applyGroupSyncFromContact(
        Entity $entity,
        Entity $contact,
        array $extraGroupNames
    ): bool
    {
        $previousTeamsIds = $this->normalizeTeamIds($entity->get('teamsIds') ?? []);

        $this->syncGroupsFromFields(
            $entity,
            self::CONTACT_TEAM_FIELDS,
            $contact,
            $extraGroupNames
        );

        $newTeamsIds = $this->normalizeTeamIds($entity->get('teamsIds') ?? []);

        return $previousTeamsIds !== $newTeamsIds;
    }

    /**
     * @param string[]|null $ids
     * @return string[]
     */
    private function normalizeTeamIds(?array $ids): array
    {
        $ids = array_values(array_unique($ids ?? []));
        sort($ids);

        return $ids;
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