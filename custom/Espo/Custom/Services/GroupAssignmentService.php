<?php



namespace Espo\Custom\Services;



use Espo\Core\ORM\Repository\Option\SaveOption;

use Espo\ORM\Entity;

use Espo\ORM\EntityManager;

use Espo\Entities\Team;





    private const SPONSOR_CHAIN_MAX_LEVELS = 3;



    /** @var array<string, string[]> */

    private array $sponsorChainGroupNamesByTeamId = [];



    /** @var array<string, string|null> */

    private array $groupIdByName = [];



    /** @var array<string, array{name: string, sponserId: ?string}>|null */

        
        $requiredGroupIds = $this->getGroupsFromFields($sourceEntity, $fieldsToWatch);
        $requiredGroupIds = array_merge($requiredGroupIds, $this->getGroupsFromNames($extraGroupNames));

    /** @var array<string, string> */
            $cTeam = $this->entityManager->getRelation($sourceEntity, 'cTeam')->findOne();
            $requiredGroupIds = array_merge(
                $requiredGroupIds,
                $this->getGroupsFromNames($this->getSponsorChainGroupNames($cTeam))
            );

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



        $this->syncLeadsAndContactsForTeam($team);
        $groupNames = array_merge($groupNames, $extraGroupNames);
        $downlines = $this->entityManager->getRelation($team, 'downlines')->find();
        foreach ($downlines as $downline) {
            $this->syncLeadsAndContactsForTeamAndDownlines($downline);
        }
    }
        if (in_array('cTeam', $fieldsToWatch, true)) {
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

            if ($cTeamId) {

                $groupNames = array_merge($groupNames, $this->getSponsorChainGroupNamesByTeamId($cTeamId));

                ->where(['cTeamId' => $teamId])

        }

                $this->entityManager->saveEntity($entity, [SaveOption::SILENT => true]);

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


     * @param string[]|null $ids

        }
    private function normalizeTeamIds(?array $ids): array

        $ids = array_values(array_unique($ids ?? []));
        sort($ids);
        foreach ($teamIds as $teamId) {
        return $ids;
    }

    private function getGroupsFromFields(Entity $entity, array $fieldsToWatch): array
                ->getRDBRepository($entityType)
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
            }
        return array_unique($requiredGroupIds);

                return $this->slimFitCenterNameById[$centerId];

            }

        }

    private function getGroupsFromNames(array $groupNames): array

        $requiredGroupIds = [];

        }



        $name = $relatedEntity->get('name');
            $group = $this->getGroupByName(trim($groupName));
            if ($group) {
                $requiredGroupIds[] = $group->getId();

        return trim($name);

        return $requiredGroupIds;
    }

    private function getGroupByName(string $groupName): ?Entity
    {
        return $this->entityManager->getRDBRepository(Team::ENTITY_TYPE)->where(['name' => $groupName])->findOne();

    private function getSponsorChainGroupNamesByTeamId(string $teamId): array

        if (array_key_exists($teamId, $this->sponsorChainGroupNamesByTeamId)) {

            return $this->sponsorChainGroupNamesByTeamId[$teamId];

        }



        $groupNames = $this->cTeamById !== null

            ? $this->resolveSponsorChainFromGraph($teamId)

            : $this->fetchSponsorChainGroupNamesByJoin($teamId);



        $this->sponsorChainGroupNamesByTeamId[$teamId] = $groupNames;



        return $groupNames;

    }



    /**

     * @return string[]

     */

    private function resolveSponsorChainFromGraph(string $teamId): array

    {

        if ($this->cTeamById === null) {

            return [];

        }



        $groupNames = [];

        $currentSponserId = $this->cTeamById[$teamId]['sponserId'] ?? null;



        for ($level = 0; $level < self::SPONSOR_CHAIN_MAX_LEVELS && $currentSponserId; $level++) {

            $sponsor = $this->cTeamById[$currentSponserId] ?? null;

            if (!$sponsor) {

                break;

            }



            $name = $sponsor['name'] ?? null;

            if (!is_string($name) || trim($name) === '') {

                break;

            }



            $groupNames[] = trim($name);

            $currentSponserId = $sponsor['sponserId'] ?? null;

        }



        return $groupNames;

    }



    /**

     * @return string[]

     */

    private function fetchSponsorChainGroupNamesByJoin(string $teamId): array

    {

        $query = $this->entityManager->getQueryBuilder()

            ->select([

                'sponsor1.name',

                'sponsor2.name',

                'sponsor3.name',

            ])

            ->from('CTeam', 'team')

            ->leftJoin('CTeam', 'sponsor1', [

                'team.sponserId:' => 'sponsor1.id',

                'sponsor1.deleted' => false,

            ])

            ->leftJoin('CTeam', 'sponsor2', [

                'sponsor1.sponserId:' => 'sponsor2.id',

                'sponsor2.deleted' => false,

            ])

            ->leftJoin('CTeam', 'sponsor3', [

                'sponsor2.sponserId:' => 'sponsor3.id',

                'sponsor3.deleted' => false,

            ])

            ->where([

                'team.id' => $teamId,

                'team.deleted' => false,

            ])

            ->build();



        $row = $this->entityManager->getQueryExecutor()

            ->execute($query)

            ->fetch(\PDO::FETCH_ASSOC);



        if (!$row) {

            return [];

        }



        $groupNames = [];

        foreach ($row as $name) {

            if (!is_string($name) || trim($name) === '') {

                continue;

            }



            $groupNames[] = trim($name);

        }



        return $groupNames;

    }



    /**

     * @return string[]

     */

    private function collectTeamAndDownlineIds(Entity $team): array

    {

        $rootId = $team->getId();

        if (!$rootId || $this->cTeamById === null) {

            return [];

        }



        $childrenBySponsor = [];

        foreach ($this->cTeamById as $teamId => $teamData) {

            $sponserId = $teamData['sponserId'] ?? null;

            if (!$sponserId) {

                continue;

            }



            $childrenBySponsor[$sponserId][] = $teamId;

        }



        $ids = [];

        $queue = [$rootId];



        while ($queue !== []) {

            $teamId = array_shift($queue);

            if (isset($ids[$teamId])) {

                continue;

            }



            $ids[$teamId] = true;



            foreach ($childrenBySponsor[$teamId] ?? [] as $childId) {

                $queue[] = $childId;

            }

        }



        return array_keys($ids);

    }



    private function ensureCTeamGraphLoaded(): void

    {

        if ($this->cTeamById !== null) {

            return;

        }



        $query = $this->entityManager->getQueryBuilder()

            ->select(['id', 'name', 'sponserId'])

            ->from('CTeam')

            ->where(['deleted' => false])

            ->build();



        $sth = $this->entityManager->getQueryExecutor()->execute($query);



        $this->cTeamById = [];

        $teamNames = [];



        foreach ($sth->fetchAll(\PDO::FETCH_ASSOC) as $row) {

            $this->cTeamById[$row['id']] = $row;



            $name = $row['name'] ?? null;

            if (is_string($name) && trim($name) !== '') {

                $teamNames[] = trim($name);

            }

        }



        if ($teamNames !== []) {

            $this->resolveGroupIdsForNames(array_unique($teamNames));

        }

    }



    /**

     * @param iterable<Entity> $entities

     */

    private function warmSlimFitCenterCacheForEntities(iterable $entities): void

    {

        $centerIds = [];



        foreach ($entities as $entity) {

            $centerId = $entity->get('cSlimFitCenterId');

            if (!$centerId || array_key_exists($centerId, $this->slimFitCenterNameById)) {

                continue;

            }



            $centerIds[$centerId] = true;

        }



        if ($centerIds === []) {

            return;

        }



        $centers = $this->entityManager

            ->getRDBRepository('CSlimFitCenter')

            ->where(['id' => array_keys($centerIds)])

            ->find();



        foreach ($centers as $center) {

            $name = $center->get('name');

            if (!is_string($name) || trim($name) === '') {

                continue;

            }



            $this->slimFitCenterNameById[$center->getId()] = trim($name);

        }

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



    private function setGroupsForEntity(Entity $entity, array $requiredGroupIds): void

    {

        $entity->setMultiple(['teamsIds' => array_values(array_unique($requiredGroupIds))]);

    }



    /**

     * @param string[] $groupNames

     * @return string[]

     */

    private function resolveGroupIdsForNames(array $groupNames): array

    {

        $normalizedNames = [];



        foreach ($groupNames as $groupName) {

            if (!is_string($groupName) || trim($groupName) === '') {

                continue;

            }



            $normalizedNames[] = trim($groupName);

        }



        $normalizedNames = array_values(array_unique($normalizedNames));

        $uncachedNames = array_values(array_filter(

            $normalizedNames,

            fn (string $name) => !array_key_exists($name, $this->groupIdByName)

        ));



        if ($uncachedNames !== []) {

            $groups = $this->entityManager

                ->getRDBRepository(Team::ENTITY_TYPE)

                ->where(['name' => $uncachedNames])

                ->find();



            foreach ($groups as $group) {

                $name = $group->get('name');

                if (!is_string($name) || trim($name) === '') {

                    continue;

                }



                $this->groupIdByName[trim($name)] = $group->getId();

            }



            foreach ($uncachedNames as $name) {

                if (!array_key_exists($name, $this->groupIdByName)) {

                    $this->groupIdByName[$name] = null;

                }

            }

        }



        $groupIds = [];

        foreach ($normalizedNames as $name) {

            $groupId = $this->groupIdByName[$name] ?? null;

            if ($groupId) {

                $groupIds[] = $groupId;

            }

        }



        return $groupIds;

    }

}


