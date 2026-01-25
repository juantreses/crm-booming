<?php

namespace Espo\Modules\LeadManager\Services;

use DateTime;
use Espo\Custom\Controllers\CLeadEvent;
use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Services\LeadEventService;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

readonly class LeadService
{
    public function __construct(
        private EntityManager $entityManager,
        private LeadEventService $leadEventService,
        private SlugService $slug,
    )
    {}

    public function findOrCreate(array $data): Entity
    {
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';

        // Try to find by email first, then by phone
        $person = $email ? $this->lookupBy('emailAddress', $email) : null;
        if (!$person && $phone) {
            $person = $this->lookupBy('phoneNumber', $phone);
        }

        if (!$person) {
            $person = $this->createLead($data);
        }

        $coachIdentifier = $data['coachId'] ?? null;
        $coachId = $this->slug->resolve('User', $coachIdentifier);
        if ($coachId) {
            $this->assignCoach($person, $coachId, $data->source ?? 'Webform');
        }

        return $person;
    }

    private function lookupBy(string $fieldName, string $value): ?Entity
    {
        if (empty($value)) {
            return null;
        }

        $contact = $this->entityManager->getRDBRepository(Contact::ENTITY_TYPE)
            ->where([$fieldName => $value])
            ->findOne();

        if ($contact) {
            return $contact;
        }

        return $this->entityManager->getRDBRepository(Lead::ENTITY_TYPE)
            ->where([$fieldName => $value])
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

    private function assignCoach(Entity $person, string $newCoachId, $source = 'Webform'): void
    {
        $oldCoachId = $person->get('cTeamId');

        // 1. Klanten (Contacts) worden NOOIT opnieuw toegewezen
        if ($person->getEntityType() === Contact::ENTITY_TYPE) {
            return;
        }

        // 2. Controleer op inactiviteit (30 dagen) bij Leads
        if ($oldCoachId && !$this->isLeadEligibleForReassignment($person)) {
            $oldCoach = $this->entityManager->getEntityById('CTeam', $oldCoachId);
            $msg = "Toewijzing gefaald: Lead is nog actief bij coach " . ($oldCoach?->get('name') ?? 'onbekend');
            
            $existingNotes = (string) ($person->get('cNotes') ?? '');
            $timezone = new \DateTimeZone('Europe/Brussels');
            $dt = new \DateTime('now', $timezone);
            $formattedHeader = $dt->format('[d/m/Y H:i]');
            $newLine = "$formattedHeader ($source): $msg";
            $updatedNotes = $existingNotes ? ($newLine . "\n\n" . $existingNotes) : $newLine;

            $person->set('cNotes', $updatedNotes);
            return;
        }

        $newCoach = $this->entityManager->getEntityById('User', $newCoachId);
        if (!$newCoach) {
            return;
        }

        $oldCoachName = $oldCoachId ? ($this->entityManager->getEntityById('CTeam', $oldCoachId)?->get('name') ?? 'Onbekend') : 'null';
        $newCoachName = $newCoach->get('name');

        $person->set('assignedUserId', $newCoachId);
        $team = $this->entityManager->getRDBRepository('CTeam')->where('assignedUserId', $newCoachId)->findOne();
        if ($team) {
            $person->set('cTeamId', $team->getId());

            if ($sfcId = $team->get('slimFitCenterId')) {
                $person->set('cSlimFitCenterId', $sfcId);
            }
        }
        
        $this->entityManager->saveEntity($person);

        $this->leadEventService->logEvent(
            $person->get('id'), 
            LeadEventType::ASSIGNED, 
            description: "{$oldCoachName} -> {$newCoachName}"
        );
    }

    private function isLeadEligibleForReassignment(Entity $lead): bool
    {
        if (!$lead->get('cTeamId')) {
            return true;
        }

        $lastEvent = $this->entityManager->getRDBRepository('CLeadEvent')
            ->where(['leadId' => $lead->get('id')])
            ->order('eventDate', 'DESC')
            ->findOne();

        $lastActivityDate = $lastEvent 
            ? new DateTime($lastEvent->get('eventDate')) 
            : new DateTime($lead->get('createdAt'));

        $thirtyDaysAgo = new DateTime('-30 days');

        return $lastActivityDate < $thirtyDaysAgo;
    }
}