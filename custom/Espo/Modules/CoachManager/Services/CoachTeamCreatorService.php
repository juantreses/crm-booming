<?php

namespace Espo\Modules\CoachManager\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\Team;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CoachTeamCreatorService
{
    private const SECURITY_GROUP_NAME = 'SEC-Coach';

    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    /**
     * Creates the Espo group, user and HBL team for a contact becoming a coach.
     *
     * @return array<string, mixed>
     */
    public function createFromContact(Entity $contact): array
    {
        $firstName = trim((string) $contact->get('firstName'));
        $lastName = trim((string) $contact->get('lastName'));
        $fullName = trim(sprintf('%s %s', $firstName, $lastName));

        if ($firstName === '' || $lastName === '') {
            throw new BadRequest('Contact first name and last name are required.');
        }

        $securityGroup = $this->getGroupByName(self::SECURITY_GROUP_NAME);

        if (!$securityGroup) {
            throw new BadRequest(sprintf('Required group "%s" was not found.', self::SECURITY_GROUP_NAME));
        }

        $coachGroup = $this->getOrCreateGroup($fullName);
        $user = $this->getLinkedUser($contact) ?? $this->createUser($contact, $coachGroup, $securityGroup);
        $cTeam = $this->getLinkedCTeam($contact) ?? $this->createCTeam($fullName, $user, $coachGroup);

        $this->ensureUserGroups($user, $coachGroup, $securityGroup);
        $this->ensureCTeamLinks($cTeam, $user, $coachGroup);
        $this->assignCoachTeamToContact($contact, $cTeam, $user);

        return [
            'success' => true,
            'contactId' => $contact->getId(),
            'userId' => $user->getId(),
            'userName' => $user->get('userName'),
            'teamId' => $coachGroup->getId(),
            'teamName' => $coachGroup->get('name'),
            'cTeamId' => $cTeam->getId(),
            'cTeamName' => $cTeam->get('name'),
        ];
    }

    private function createUser(Entity $contact, Entity $coachGroup, Entity $securityGroup): Entity
    {
        $firstName = trim((string) $contact->get('firstName'));
        $lastName = trim((string) $contact->get('lastName'));

        $user = $this->entityManager->getNewEntity('User');
        $user->set([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'userName' => $this->createUniqueUserName($firstName, $lastName),
            'type' => 'regular',
            'isActive' => true,
            'defaultTeamId' => $coachGroup->getId(),
            'teamsIds' => [$securityGroup->getId(), $coachGroup->getId()],
            'cSlug' => $this->slugify($firstName),
            'cMemberId' => $contact->getId(),
        ]);

        if ($this->entityManager->getDefs()->getEntity('User')->hasField('functions')) {
            $user->set('functions', ['coach', 'klant']);
        }

        $this->entityManager->saveEntity($user);

        return $user;
    }

    private function createCTeam(string $name, Entity $user, Entity $coachGroup): Entity
    {
        $team = $this->entityManager->getNewEntity('CTeam');
        $team->set([
            'name' => $name,
            'assignedUserId' => $user->getId(),
            'teamsIds' => [$coachGroup->getId()],
        ]);

        $this->entityManager->saveEntity($team);

        return $team;
    }

    private function assignCoachTeamToContact(Entity $contact, Entity $cTeam, Entity $user): void
    {
        $contact->set([
            'cTeamId' => $cTeam->getId(),
            'assignedUserId' => $user->getId(),
        ]);

        $this->entityManager->saveEntity($contact);
    }

    private function ensureUserGroups(Entity $user, Entity $coachGroup, Entity $securityGroup): void
    {
        $teamsIds = $user->get('teamsIds') ?? [];
        $teamsIds[] = $securityGroup->getId();
        $teamsIds[] = $coachGroup->getId();

        $user->set([
            'teamsIds' => array_values(array_unique($teamsIds)),
            'defaultTeamId' => $coachGroup->getId(),
        ]);

        $this->entityManager->saveEntity($user);
    }

    private function ensureCTeamLinks(Entity $cTeam, Entity $user, Entity $coachGroup): void
    {
        $cTeam->set([
            'assignedUserId' => $user->getId(),
            'teamsIds' => array_values(array_unique(array_merge($cTeam->get('teamsIds') ?? [], [$coachGroup->getId()]))),
        ]);

        $this->entityManager->saveEntity($cTeam);

        $this->entityManager
            ->getRDBRepository('CTeam')
            ->getRelation($cTeam, 'assignedUsers')
            ->relate($user);
    }

    private function getOrCreateGroup(string $name): Entity
    {
        $group = $this->getGroupByName($name);

        if ($group) {
            return $group;
        }

        $group = $this->entityManager->getNewEntity(Team::ENTITY_TYPE);
        $group->set('name', $name);

        $this->entityManager->saveEntity($group);

        return $group;
    }

    private function getGroupByName(string $name): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository(Team::ENTITY_TYPE)
            ->where(['name' => $name])
            ->findOne();
    }

    private function getLinkedUser(Entity $contact): ?Entity
    {
        return $this->entityManager->getRelation($contact, 'cUser')->findOne();
    }

    private function getLinkedCTeam(Entity $contact): ?Entity
    {
        if (!$contact->get('cTeamId')) {
            return null;
        }

        return $this->entityManager->getEntityById('CTeam', $contact->get('cTeamId'));
    }

    private function createUniqueUserName(string $firstName, string $lastName): string
    {
        $base = $this->slugify($firstName . $lastName);
        $candidate = $base;
        $counter = 2;

        while ($this->userNameExists($candidate)) {
            $candidate = $base . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function userNameExists(string $userName): bool
    {
        return (bool) $this->entityManager
            ->getRDBRepository('User')
            ->where(['userName' => $userName])
            ->findOne();
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';

        return $value;
    }
}
