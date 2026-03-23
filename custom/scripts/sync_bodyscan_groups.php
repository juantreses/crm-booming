<?php

declare(strict_types=1);

use Espo\Core\Application;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\Entities\Team;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

include dirname(__DIR__, 2) . '/bootstrap.php';

$isDryRun = in_array('--dry-run', $argv ?? [], true);

$application = new Application();
$application->setupSystemUser();

$container = $application->getContainer();

/** @var EntityManager $entityManager */
$entityManager = $container->getByClass(EntityManager::class);

$groupAssignmentService = new GroupAssignmentService($entityManager);


$teamRepo = $entityManager->getRDBRepository(Team::ENTITY_TYPE);
$contactRepo = $entityManager->getRDBRepository('Contact');

$totalTeams = 0;
$matchedContacts = 0;
$processedBodyscans = 0;
$updatedBodyscans = 0;
$teamsWithoutSplit = 0;


/** @var iterable<Entity> $teams */
$teams = $teamRepo->where(['deleted' => false])->find();

foreach ($teams as $team) {
    $totalTeams++;

    $teamName = trim((string) $team->get('name'));
    if ($teamName === '') {
        continue;
    }

    $nameParts = explode(' ', $teamName, 2);
    $firstName = trim($nameParts[0] ?? '');
    $lastName = trim($nameParts[1] ?? '');

    if ($firstName === '' || $lastName === '') {
        $teamsWithoutSplit++;
        continue;
    }

    /** @var ?Entity $contact */
    $contact = $contactRepo
        ->where([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'deleted' => false,
        ])
        ->findOne();

    if (!$contact) {
        continue;
    }

    $matchedContacts++;

    /** @var iterable<Entity> $bodyscans */
    $bodyscans = $entityManager->getRelation($contact, 'cBodyscans')->find();

    foreach ($bodyscans as $bodyscan) {
        $processedBodyscans++;

        $beforeTeamIds = $bodyscan->get('teamsIds') ?? [];
        if (!is_array($beforeTeamIds)) {
            $beforeTeamIds = [];
        }
        sort($beforeTeamIds);

        $groupAssignmentService->syncGroupsFromFields(
            $bodyscan,
            ['cSlimFitCenter', 'cTeam'],
            $contact,
            [$teamName]
        );

        $afterTeamIds = $bodyscan->get('teamsIds') ?? [];
        if (!is_array($afterTeamIds)) {
            $afterTeamIds = [];
        }
        sort($afterTeamIds);

        if ($beforeTeamIds !== $afterTeamIds) {
            $updatedBodyscans++;

            if (!$isDryRun) {
                $entityManager->saveEntity($bodyscan);
            }
        }
    }
}

echo "Bodyscan group sync completed.\n";
echo "Mode: " . ($isDryRun ? 'DRY RUN' : 'APPLY') . "\n";
echo "Teams scanned: {$totalTeams}\n";
echo "Teams skipped (cannot split to first+last): {$teamsWithoutSplit}\n";
echo "Teams matched to contact by firstName+lastName: {$matchedContacts}\n";
echo "Bodyscans checked: {$processedBodyscans}\n";
echo "Bodyscans updated: {$updatedBodyscans}\n";

