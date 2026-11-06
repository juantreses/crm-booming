<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Services;

use Espo\Core\ORM\Helper;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\LeadManager\Services\LeadFromWidgetService;
use Espo\Modules\LeadManager\Services\LeadService;
use Espo\Modules\LeadManager\Services\PhoneFormatterService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class LeadServiceTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var list<string> "Type where" per query, e.g. "Contact emailAddress=jan@test.be" */
    private array $queries = [];

    /** @var array<string, Entity> query => result */
    private array $queryResults = [];

    /** @var list<string> "event.link -> target" per relate() */
    private array $related = [];

    protected function setUp(): void
    {
        $this->addRecord(FakeEntity::create('User', ['id' => 'coach-new', 'name' => 'Nieuwe Coach']));
        $this->addRecord(FakeEntity::create('CTeam', ['id' => 'team-old', 'name' => 'Team Oud', 'assignedUserId' => 'coach-old']));
        $this->queryResults['CTeam assignedUserId=coach-new'] = FakeEntity::create(
            'CTeam',
            ['id' => 'team-new', 'assignedUserId' => 'coach-new', 'slimFitCenterId' => 'sfc1'],
        );
    }

    public function testFindsContactByEmailFirst(): void
    {
        $contact = FakeEntity::create('Contact', ['id' => 'contact1']);
        $this->queryResults['Contact emailAddress=jan@test.be'] = $contact;

        $result = $this->service()->findOrCreate(['email' => 'jan@test.be', 'phone' => '0470123456', 'coachId' => 'nieuwe-coach']);

        self::assertSame($contact, $result);
        self::assertSame(['Contact emailAddress=jan@test.be'], $this->queries);
        // Contacts are never (re)assigned to a coach.
        self::assertNull($contact->get('assignedUserId'));
        self::assertSame([], $this->savedEntities());
    }

    public function testFallsBackToLeadByFormattedPhone(): void
    {
        $lead = FakeEntity::create('Lead', ['id' => 'lead1']);
        $this->queryResults['Lead phoneNumber=+32470123456'] = $lead;

        $result = $this->service()->findOrCreate(['email' => 'onbekend@test.be', 'phone' => '0470 12 34 56']);

        self::assertSame($lead, $result);
        self::assertSame([
            'Contact emailAddress=onbekend@test.be',
            'Lead emailAddress=onbekend@test.be',
            'Contact phoneNumber=+32470123456',
            'Lead phoneNumber=+32470123456',
        ], $this->queries);
    }

    public function testCreatesLeadWhenNothingFound(): void
    {
        $lead = $this->service()->findOrCreate([
            'firstName' => 'Jan',
            'lastName' => 'Peeters',
            'email' => 'jan@test.be',
            'phone' => '0470 12 34 56',
            'sourceUrl' => 'https://site.test/form',
        ]);

        self::assertSame('Lead', $lead->getEntityType());
        self::assertSame('Jan', $lead->get('firstName'));
        self::assertSame('Peeters', $lead->get('lastName'));
        self::assertSame('jan@test.be', $lead->get('emailAddress'));
        self::assertSame('+32470123456', $lead->get('phoneNumber'));
        self::assertSame('Webform', $lead->get('source'));
        self::assertSame('https://site.test/form', $lead->get('cSourceUrl'));
        self::assertSame(['Lead:new1'], $this->savedEntities());
    }

    public function testNewLeadIsAssignedToCoach(): void
    {
        $lead = $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach', 'source' => 'Survey']);

        self::assertSame('coach-new', $lead->get('assignedUserId'));
        self::assertSame('team-new', $lead->get('cTeamId'));
        self::assertSame('Assigned', $lead->get('status'));
        self::assertSame('to_call', $lead->get('cStage'));
        self::assertSame('sfc1', $lead->get('cSlimFitCenterId'));
        self::assertSame('Survey', $lead->get('source'));

        $event = $this->created[1];
        self::assertSame('CLeadEvent', $event->getEntityType());
        self::assertSame('assigned', $event->get('eventType'));
        self::assertSame('null -> Nieuwe Coach', $event->get('description'));
        self::assertSame(['new2.lead -> new1'], $this->related);
    }

    public function testAssignmentKeepsExistingStage(): void
    {
        $lead = FakeEntity::create('Lead', ['id' => 'lead1', 'cStage' => 'follow_up']);
        $this->queryResults['Lead emailAddress=jan@test.be'] = $lead;

        $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach']);

        self::assertSame('follow_up', $lead->get('cStage'));
        self::assertSame('team-new', $lead->get('cTeamId'));
    }

    public function testLeadAlreadyWithThisCoachIsLeftAlone(): void
    {
        $lead = FakeEntity::create('Lead', ['id' => 'lead1', 'assignedUserId' => 'coach-new']);
        $this->queryResults['Lead emailAddress=jan@test.be'] = $lead;

        $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach']);

        self::assertSame([], $this->savedEntities());
    }

    public function testLeadInTeamOfThisCoachIsLeftAlone(): void
    {
        $this->addRecord(FakeEntity::create('CTeam', ['id' => 'team-new', 'assignedUserId' => 'coach-new']));
        $lead = FakeEntity::create('Lead', ['id' => 'lead1', 'cTeamId' => 'team-new']);
        $this->queryResults['Lead emailAddress=jan@test.be'] = $lead;

        $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach']);

        self::assertSame([], $this->savedEntities());
    }

    public function testRecentlyActiveLeadOfAnotherCoachIsNotReassigned(): void
    {
        $lead = FakeEntity::create('Lead', ['id' => 'lead1', 'cTeamId' => 'team-old', 'assignedUserId' => 'coach-old', 'cNotes' => 'Oud']);
        $this->queryResults['Lead emailAddress=jan@test.be'] = $lead;
        $this->queryResults['CLeadEvent leadId=lead1'] = FakeEntity::create('CLeadEvent', ['eventDate' => gmdate('Y-m-d H:i:s', strtotime('-10 days'))]);

        $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach', 'source' => 'Survey']);

        self::assertSame('coach-old', $lead->get('assignedUserId'));
        self::assertSame('team-old', $lead->get('cTeamId'));
        self::assertMatchesRegularExpression(
            '/^\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}\] \(Survey\): Toewijzing gefaald: Lead is nog actief bij coach Team Oud\n\nOud$/',
            $lead->get('cNotes'),
        );
        self::assertSame(['Lead:lead1'], $this->savedEntities());
    }

    public function testLeadOfAnotherCoachInactiveForThirtyDaysIsReassigned(): void
    {
        $lead = FakeEntity::create('Lead', ['id' => 'lead1', 'cTeamId' => 'team-old', 'assignedUserId' => 'coach-old']);
        $this->queryResults['Lead emailAddress=jan@test.be'] = $lead;
        $this->queryResults['CLeadEvent leadId=lead1'] = FakeEntity::create('CLeadEvent', ['eventDate' => gmdate('Y-m-d H:i:s', strtotime('-31 days'))]);

        $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach']);

        self::assertSame('coach-new', $lead->get('assignedUserId'));
        self::assertSame('team-new', $lead->get('cTeamId'));
        self::assertSame('Team Oud -> Nieuwe Coach', $this->created[0]->get('description'));
    }

    public function testLeadActiveTwentyNineDaysAgoIsStillNotReassigned(): void
    {
        $lead = FakeEntity::create('Lead', ['id' => 'lead1', 'cTeamId' => 'team-old']);
        $this->queryResults['Lead emailAddress=jan@test.be'] = $lead;
        $this->queryResults['CLeadEvent leadId=lead1'] = FakeEntity::create('CLeadEvent', ['eventDate' => gmdate('Y-m-d H:i:s', strtotime('-29 days'))]);

        $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach']);

        self::assertSame('team-old', $lead->get('cTeamId'));
    }

    public function testWithoutEventsTheCreationDateCountsAsLastActivity(): void
    {
        $lead = FakeEntity::create('Lead', [
            'id' => 'lead1',
            'cTeamId' => 'team-old',
            'createdAt' => gmdate('Y-m-d H:i:s', strtotime('-5 days')),
        ]);
        $this->queryResults['Lead emailAddress=jan@test.be'] = $lead;

        $this->service()->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'nieuwe-coach']);

        self::assertSame('team-old', $lead->get('cTeamId'));
    }

    public function testUnknownCoachSlugAssignsNobody(): void
    {
        $lead = $this->service(coachSlug: null)->findOrCreate(['email' => 'jan@test.be', 'coachId' => 'onbekend']);

        self::assertNull($lead->get('assignedUserId'));
        self::assertSame(['Lead:new1'], $this->savedEntities());
    }

    public function testWidgetSubmissionRequiresEmail(): void
    {
        $result = (new LeadFromWidgetService($this->inMemoryEntityManager(), $this->service()))
            ->createFromSubmission(['firstName' => 'Jan']);

        self::assertSame(['success' => false, 'error' => 'Email is verplicht'], $result);
        self::assertSame([], $this->savedEntities());
    }

    public function testWidgetSubmissionStoresSurveyAnswers(): void
    {
        $result = (new LeadFromWidgetService($this->inMemoryEntityManager(), $this->service()))->createFromSubmission([
            'email' => 'jan@test.be',
            'ontbijt' => ['Brood'],
            'ontbijtAndere' => '',
            'score' => 7,
            'sport' => [],
            'interests' => ['Gewicht'],
            'opmerking' => 'Bel na 18u',
            'referredBy' => 'Marie',
        ]);

        self::assertSame(['success' => true, 'id' => 'new1'], $result);

        $lead = $this->created[0];
        self::assertSame(
            ['Wat had je vanmorgen als ontbijt?' => ['Brood'], 'Gezondheidsscore' => 7, 'Waarin ben je geïnteresseerd?' => ['Gewicht'], 'Opmerking' => 'Bel na 18u'],
            json_decode($lead->get('cSurveyData'), true),
        );
        self::assertStringContainsString('geïnteresseerd', $lead->get('cSurveyData'));
        self::assertSame('Marie', $lead->get('cReferredBy'));
        self::assertSame(['Lead:new1', 'Lead:new1'], $this->savedEntities());
    }

    public function testWidgetSurveyDropsScoreZero(): void
    {
        // array_filter() without callback also removes 0, so a health score of 0 is not stored.
        (new LeadFromWidgetService($this->inMemoryEntityManager(), $this->service()))
            ->createFromSubmission(['email' => 'jan@test.be', 'score' => 0]);

        self::assertSame([], json_decode($this->created[0]->get('cSurveyData'), true));
    }

    /**
     * LeadService::createLead() returns the real Lead class.
     */
    protected function newEntity(string $entityType, string $id): Entity
    {
        if ($entityType !== 'Lead') {
            return FakeEntity::create($entityType, ['id' => $id]);
        }

        $lead = new Lead('Lead', FakeEntity::defs(), $this->createStub(EntityManager::class), $this->createStub(Helper::class));
        $lead->set('id', $id);

        return $lead;
    }

    private function service(?string $coachSlug = 'coach-new'): LeadService
    {
        $entityManager = $this->inMemoryEntityManager();
        $entityManager->method('getRDBRepository')->willReturnCallback(fn (string $type) => $this->repository($type));

        $slugService = $this->createStub(SlugService::class);
        $slugService->method('resolve')->willReturnCallback(
            static fn (string $type, ?string $identifier) => $identifier === null ? null : $coachSlug,
        );

        return new LeadService($entityManager, $slugService, new PhoneFormatterService());
    }

    private function repository(string $type): RDBRepository
    {
        $repository = $this->createStub(RDBRepository::class);

        $repository->method('where')->willReturnCallback(function (array|string $clause, mixed $value = null) use ($type): RDBSelectBuilder {
            $where = is_array($clause)
                ? implode(',', array_map(static fn ($k, $v) => "$k=$v", array_keys($clause), $clause))
                : "$clause=$value";
            $query = "$type $where";

            if ($type === 'Contact' || $type === 'Lead') {
                $this->queries[] = $query;
            }

            $builder = $this->createStub(RDBSelectBuilder::class);
            $builder->method('order')->willReturnSelf();
            $builder->method('findOne')->willReturn($this->queryResults[$query] ?? null);

            return $builder;
        });

        $repository->method('getRelation')->willReturnCallback(function (Entity $event, string $link): RDBRelation {
            $relation = $this->createStub(RDBRelation::class);
            $relation->method('relate')->willReturnCallback(function (Entity $target) use ($event, $link): void {
                $this->related[] = "{$event->getId()}.$link -> {$target->getId()}";
            });

            return $relation;
        });

        return $repository;
    }
}
