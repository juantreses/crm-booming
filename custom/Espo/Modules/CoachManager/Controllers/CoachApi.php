<?php

namespace Espo\Modules\CoachManager\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\CoachManager\Services\CoachTeamCreatorService;
use Espo\ORM\EntityManager;

readonly class CoachApi
{
    public function __construct(
        private EntityManager $entityManager,
        private CoachTeamCreatorService $coachTeamCreatorService,
    ) {}

    public function postActionCreateTeam(Request $request): array
    {
        $contactId = $request->getRouteParam('id');

        if (!$contactId) {
            throw new BadRequest('Contact ID is required.');
        }

        $contact = $this->entityManager->getEntityById('Contact', $contactId);

        if (!$contact) {
            throw new BadRequest('Contact not found.');
        }

        return $this->coachTeamCreatorService->createFromContact($contact);
    }
}
