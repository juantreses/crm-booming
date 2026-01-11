<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Core\Utils\Log;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Services\LeadEventService;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

readonly class LeadService
{
    public function __construct(
        private EntityManager $entityManager,
        private LeadEventService $leadEventService,
    )
    {}

    public function findOrCreate(array $data): Entity
    {
        $email = $data['email'] ?? '';

        $person = $this->lookupByEmail($email);

        if (!$person) {
            $person = $this->createLead($data);
        }

        $coachId = $data['coachId'] ?? null;
        if ($coachId) {
            $this->assignCoach($person, $coachId);
        }

        return $person;
    }

    private function lookupByEmail(string $email): ?Entity
    {
        if (empty($email)) {
            return null;
        }

        $contact = $this->entityManager->getRDBRepository(Contact::ENTITY_TYPE)
            ->where(['emailAddress' => $email])
            ->findOne();

        if ($contact) {
            return $contact;
        }

        return $this->entityManager->getRDBRepository(Lead::ENTITY_TYPE)
            ->where(['emailAddress' => $email])
            ->findOne();
    }

    private function createLead(array $data): Lead
    {
        /** @var Lead $lead */
        $lead = $this->entityManager->getNewEntity(Lead::ENTITY_TYPE);

        $lead->set([
            'firstName' => $data['firstName'] ?? '',
            'lastName' => $data['lastName'] ?? '',
            'emailAddress' => $data['email'] ?? '',
            'phoneNumber' => $data['phone'] ?? '',
            'source' => $data['source'] ?? 'Webform',
        ]);

        $this->entityManager->saveEntity($lead);
        return $lead;
    }

    private function assignCoach(Entity $person, string $coachId): void
    {
        if ($person->get('cTeamId') === $coachId) {
            return; 
        }

        $coach = $this->entityManager->getEntityById('CTeam', $coachId);
        if (!$coach) {
            return;
        }

        $person->set('cTeamId', $coachId);
        if ($sfcId = $coach->get('slimFitCenterId')) {
            $person->set('cSlimFitCenterId', $sfcId);
        }

        if ($person->getEntityType() === Lead::ENTITY_TYPE) {
            $this->leadEventService->logEvent($person->get('id'), LeadEventType::ASSIGNED);
        }

        $this->entityManager->saveEntity($person);
    }
}