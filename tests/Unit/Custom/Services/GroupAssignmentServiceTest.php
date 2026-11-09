<?php

declare(strict_types=1);

namespace Tests\Unit\Custom\Services;

use Espo\Custom\Services\GroupAssignmentService;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class GroupAssignmentServiceTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var array<string, Entity|list<Entity>|null> "entityId.link" => related entity (findOne) or entities (find) */
    private array $relations = [];

    /** @var array<string, string> Espo team (group) name => id */
    private array $groups = [
        'SFC Gent' => 'g-sfc',
        'Team Jan' => 'g-team-jan',
        'Sponsor 1' => 'g-s1',
        'Sponsor 2' => 'g-s2',
        'Sponsor 3' => 'g-s3',
        'Sponsor 4' => 'g-s4',
        'Jan Peeters' => 'g-coach-jan',
    ];

    /** @var array<string, list<Entity>> "Type cTeamId" => entities */
    private array $teamMembers = [];

    /** @var list<array<string, mixed>> */
    private array $saveOptions = [];

    protected function setUp(): void
    {
        $this->relations['lead1.cSlimFitCenter'] = FakeEntity::create('CSlimFitCenter', ['id' => 'sfc1', 'name' => 'SFC Gent']);
        $this->relations['lead1.cTeam'] = $this->team('team-jan', 'Team Jan', 'coach-jan');
        $this->sponsorChain('team-jan', ['Sponsor 1', 'Sponsor 2', 'Sponsor 3', 'Sponsor 4']);
    }

    public function testGroupsFromCenterTeamAndThreeSponsorLevels(): void
    {
        $lead = $this->entity('Lead', 'lead1', ['g-manual']);

        $this->service()->syncGroupsFromFields($lead, ['cSlimFitCenter', 'cTeam']);

        // Manually added groups are replaced on purpose; the fourth sponsor level is not included.
        self::assertSame(['g-sfc', 'g-team-jan', 'g-s1', 'g-s2', 'g-s3'], $lead->get('teamsIds'));
    }

    public function testSponsorChainOnlyWhenTheTeamIsWatched(): void
    {
        $lead = $this->entity('Lead', 'lead1');

        $this->service()->syncGroupsFromFields($lead, ['cSlimFitCenter']);

        self::assertSame(['g-sfc'], $lead->get('teamsIds'));
    }

    public function testSponsorChainStopsAtASponsorWithoutName(): void
    {
        $this->sponsorChain('team-jan', ['Sponsor 1', '  ', 'Sponsor 3']);
        $lead = $this->entity('Lead', 'lead1');

        $this->service()->syncGroupsFromFields($lead, ['cTeam']);

        self::assertSame(['g-team-jan', 'g-s1'], $lead->get('teamsIds'));
    }

    public function testRelatedRecordsWithoutNameOrGroupAddNothing(): void
    {
        $this->relations['lead1.cSlimFitCenter'] = FakeEntity::create('CSlimFitCenter', ['id' => 'sfc2', 'name' => 'Center zonder groep']);
        $this->relations['lead1.cTeam'] = $this->team('team-x', null, null);
        $lead = $this->entity('Lead', 'lead1', ['g-manual']);

        $this->service()->syncGroupsFromFields($lead, ['cSlimFitCenter', 'cTeam']);

        self::assertSame([], $lead->get('teamsIds'));
    }

    public function testNothingLinked(): void
    {
        $this->relations = [];
        $lead = $this->entity('Lead', 'lead1', ['g-manual']);

        $this->service()->syncGroupsFromFields($lead, ['cSlimFitCenter', 'cTeam']);

        self::assertSame([], $lead->get('teamsIds'));
    }

    public function testGroupsFromAnotherRecordAndExtraNames(): void
    {
        $note = $this->entity('CNote', 'note1');

        $this->service()->syncGroupsFromFields(
            $note,
            ['cSlimFitCenter'],
            $this->entity('Contact', 'lead1'),
            [' Jan Peeters ', '', 'Onbekende groep', 'SFC Gent'],
        );

        // Group IDs are kept unique.
        self::assertSame(['g-sfc', 'g-coach-jan'], $note->get('teamsIds'));
    }

    public function testAssignedUserFromTheTeam(): void
    {
        $lead = $this->entity('Lead', 'lead1');
        $lead->set('assignedUserId', 'old-user');

        $this->service()->syncAssignedUserFromTeamFields($lead);

        self::assertSame('coach-jan', $lead->get('assignedUserId'));
    }

    public function testAssignedUserUnchangedWithoutTeamOrTeamUser(): void
    {
        $service = $this->service();

        $this->relations['lead1.cTeam'] = $this->team('team-x', 'Team X', null);
        $lead = $this->entity('Lead', 'lead1');
        $lead->set('assignedUserId', 'old-user');
        $service->syncAssignedUserFromTeamFields($lead);
        self::assertSame('old-user', $lead->get('assignedUserId'));

        $this->relations['lead1.cTeam'] = null;
        $service->syncAssignedUserFromTeamFields($lead);
        self::assertSame('old-user', $lead->get('assignedUserId'));
    }

    public function testAssignedUserFromTheTeamOfAnotherRecord(): void
    {
        $bodyscan = $this->entity('CBodyscan', 'scan1');

        $this->service()->syncAssignedUserFromTeamFields($bodyscan, $this->entity('Contact', 'lead1'));

        self::assertSame('coach-jan', $bodyscan->get('assignedUserId'));
    }

    public function testCoachGroupNameFromContact(): void
    {
        $service = $this->service();

        self::assertSame([], $service->getCoachExtraGroupNamesFromContact(null));
        self::assertSame(['Jan Peeters'], $service->getCoachExtraGroupNamesFromContact(
            FakeEntity::create('Contact', ['id' => 'c1', 'firstName' => 'Jan', 'lastName' => 'Peeters']),
        ));
        self::assertSame(['Jan'], $service->getCoachExtraGroupNamesFromContact(
            FakeEntity::create('Contact', ['id' => 'c1', 'firstName' => 'Jan', 'lastName' => null]),
        ));
        self::assertSame([], $service->getCoachExtraGroupNamesFromContact(
            FakeEntity::create('Contact', ['id' => 'c1', 'firstName' => '', 'lastName' => '']),
        ));
    }

    public function testResavesLeadsAndContactsOfTheTeamAndAllDownlines(): void
    {
        $top = $this->team('team-top', 'Top', null);
        $middle = $this->team('team-middle', 'Middle', null);
        $bottom = $this->team('team-bottom', 'Bottom', null);
        $this->relations['team-top.downlines'] = [$middle];
        $this->relations['team-middle.downlines'] = [$bottom];
        $this->relations['team-bottom.downlines'] = [];
        $this->teamMembers = [
            'Lead team-top' => [FakeEntity::create('Lead', ['id' => 'lead-top'])],
            'Contact team-top' => [FakeEntity::create('Contact', ['id' => 'contact-top'])],
            'Lead team-bottom' => [FakeEntity::create('Lead', ['id' => 'lead-bottom-1']), FakeEntity::create('Lead', ['id' => 'lead-bottom-2'])],
        ];

        $this->service()->syncLeadsAndContactsForTeamAndDownlines($top);

        self::assertSame(['Lead:lead-top', 'Contact:contact-top', 'Lead:lead-bottom-1', 'Lead:lead-bottom-2'], $this->savedEntities());
        // Saved silently, so the save hooks re-apply the groups without notifications.
        self::assertSame(array_fill(0, 4, ['silent' => true]), $this->saveOptions);
    }

    public function testRelatedBodyscansAndNotesFollowTheContact(): void
    {
        $contact = FakeEntity::create('Contact', ['id' => 'c1', 'firstName' => 'Jan', 'lastName' => 'Peeters']);
        $this->relations['c1.cSlimFitCenter'] = $this->relations['lead1.cSlimFitCenter'];
        $this->relations['c1.cTeam'] = $this->relations['lead1.cTeam'];
        $this->relations['c1.cBodyscans'] = [
            $this->entity('CBodyscan', 'scan-outdated', ['g-old']),
            // Already up to date, in another order: not saved again.
            $this->entity('CBodyscan', 'scan-current', ['g-coach-jan', 'g-s3', 'g-s2', 'g-s1', 'g-team-jan', 'g-sfc']),
        ];
        $this->relations['c1.cNotes'] = [$this->entity('CNote', 'note1', ['g-sfc'])];

        $this->service()->syncRelatedTeamsFromContact($contact);

        // Bodyscans also get the coach's own group; notes don't.
        self::assertSame(['g-sfc', 'g-team-jan', 'g-coach-jan', 'g-s1', 'g-s2', 'g-s3'], $this->relations['c1.cBodyscans'][0]->get('teamsIds'));
        self::assertSame(['g-sfc', 'g-team-jan', 'g-s1', 'g-s2', 'g-s3'], $this->relations['c1.cNotes'][0]->get('teamsIds'));
        self::assertSame(['CBodyscan:scan-outdated', 'CNote:note1'], $this->savedEntities());
        self::assertSame([['silent' => true], ['silent' => true]], $this->saveOptions);
    }

    /**
     * @param list<string> $teamsIds
     */
    private function entity(string $type, string $id, array $teamsIds = []): Entity
    {
        return FakeEntity::create($type, ['id' => $id, 'teamsIds' => $teamsIds, 'assignedUserId' => null]);
    }

    private function team(string $id, ?string $name, ?string $assignedUserId): Entity
    {
        return FakeEntity::create('CTeam', ['id' => $id, 'name' => $name, 'assignedUserId' => $assignedUserId]);
    }

    /**
     * @param list<string> $names
     */
    private function sponsorChain(string $teamId, array $names): void
    {
        $current = $teamId;
        foreach ($names as $i => $name) {
            $sponsorId = "sponsor-$teamId-$i";
            $this->relations["$current.sponser"] = $this->team($sponsorId, $name, null);
            $current = $sponsorId;
        }
        $this->relations["$current.sponser"] = null;
    }

    private function service(): GroupAssignmentService
    {
        return new GroupAssignmentService($this->entityManager());
    }

    private function entityManager(): EntityManager&Stub
    {
        $entityManager = $this->inMemoryEntityManager();

        $entityManager->method('getRelation')->willReturnCallback(function (Entity $entity, string $link): RDBRelation {
            $related = $this->relations["{$entity->getId()}.$link"] ?? null;
            $relation = $this->createStub(RDBRelation::class);
            $relation->method('findOne')->willReturnCallback(fn () => is_array($related) ? ($related[0] ?? null) : $related);
            $relation->method('find')->willReturnCallback(fn () => new EntityCollection(is_array($related) ? $related : []));

            return $relation;
        });

        $entityManager->method('getRDBRepository')->willReturnCallback(function (string $type): RDBRepository {
            $repository = $this->createStub(RDBRepository::class);
            $repository->method('where')->willReturnCallback(function (array $where) use ($type): RDBSelectBuilder {
                $builder = $this->createStub(RDBSelectBuilder::class);

                if ($type === 'Team') {
                    $id = $this->groups[$where['name']] ?? null;
                    $builder->method('findOne')->willReturn($id ? FakeEntity::create('Team', ['id' => $id, 'name' => $where['name']]) : null);
                } else {
                    $builder->method('find')->willReturn(new EntityCollection($this->teamMembers["$type {$where['cTeamId']}"] ?? []));
                }

                return $builder;
            });

            return $repository;
        });

        // Runs next to the trait's own saveEntity callback, which records the saved entities.
        $entityManager->method('saveEntity')->willReturnCallback(function (Entity $entity, array $options = []): void {
            $this->saveOptions[] = $options;
        });

        return $entityManager;
    }
}
