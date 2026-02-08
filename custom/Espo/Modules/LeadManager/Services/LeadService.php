<?php

namespace Espo\Modules\LeadManager\Services;

use DateTime;
use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\Crm\Entities\Lead;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

readonly class LeadService
{
    public function __construct(
        private EntityManager $entityManager,
        private SlugService $slug,
        private PhoneFormatterService $phoneFormatter,
    ) {}

    public function findOrCreate(array $data): Entity
    {
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';

        $formattedPhone = $this->phoneFormatter->format($phone);

        // Try to find by email first, then by phone
        $person = $email ? $this->lookupBy('emailAddress', $email) : null;
        if (!$person && $formattedPhone) {
            $person = $this->lookupBy('phoneNumber', $formattedPhone);
        }

        if (!$person) {
            $person = $this->createLead($data);
        }

        $coachIdentifier = $data['coachId'] ?? null;
        $coachId = $this->slug->resolve('User', $coachIdentifier);
        if ($coachId) {
            $this->assignCoach($person, $coachId, $data['source'] ?? 'Webform');
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

        $formattedPhone = $this->phoneFormatter->format($data['phone'] ?? '');

        $lead->set([
            'firstName' => $data['firstName'] ?? '',
            'lastName' => $data['lastName'] ?? '',
            'emailAddress' => $data['email'] ?? '',
            'phoneNumber' => $formattedPhone,
            'source' => $data['source'] ?? 'Webform',
        ]);

        $this->entityManager->saveEntity($lead);
        return $lead;
    }

    private function assignCoach(Entity $person, string $newCoachId, string $source = 'Webform'): void
    {
        $oldCoachId = $person->get('cTeamId');

        if ($person->getEntityType() === Contact::ENTITY_TYPE) {
            return;
        }

        if ($oldCoachId && !$this->isLeadEligibleForReassignment($person)) {
            $this->logReassignmentFailure($person, $oldCoachId, $source);
            return;
        }

        $newCoach = $this->entityManager->getEntityById('User', $newCoachId);
        if (!$newCoach) {
            return;
        }

        $this->performAssignment($person, $newCoachId, $oldCoachId, $newCoach->get('name'));
    }

    private function logReassignmentFailure(Entity $person, string $oldCoachId, string $source): void
    {
        $oldCoach = $this->entityManager->getEntityById('CTeam', $oldCoachId);
        $msg = "Toewijzing gefaald: Lead is nog actief bij coach " . ($oldCoach?->get('name') ?? 'onbekend');
        
        $existingNotes = (string) ($person->get('cNotes') ?? '');
        $timezone = new \DateTimeZone('Europe/Brussels');
        $dt = new \DateTime('now', $timezone);
        $formattedHeader = $dt->format('[d/m/Y H:i]');
        $newLine = "$formattedHeader ($source): $msg";
        $updatedNotes = $existingNotes ? ($newLine . "\n\n" . $existingNotes) : $newLine;

        $person->set('cNotes', $updatedNotes);
        $this->entityManager->saveEntity($person);
    }

    private function performAssignment(Entity $person, string $newCoachId, ?string $oldCoachId, string $newCoachName): void
    {
        $oldCoachName = $oldCoachId 
            ? ($this->entityManager->getEntityById('CTeam', $oldCoachId)?->get('name') ?? 'Onbekend') 
            : 'null';

        $person->set('assignedUserId', $newCoachId);
        
        $team = $this->entityManager->getRDBRepository('CTeam')
            ->where('assignedUserId', $newCoachId)
            ->findOne();
            
        if ($team) {
            $person->set('cTeamId', $team->getId());
            $person->set('status', LeadEventType::ASSIGNED->value);

            if ($sfcId = $team->get('slimFitCenterId')) {
                $person->set('cSlimFitCenterId', $sfcId);
            }
        }
        
        $this->entityManager->saveEntity($person);

        $this->createAssignmentEvent($person, $oldCoachName, $newCoachName);
    }

    private function createAssignmentEvent(Entity $person, string $oldCoachName, string $newCoachName): void
    {
        $event = $this->entityManager->getNewEntity('CLeadEvent');
        $timezone = new \DateTimeZone('UTC');
        $dt = new \DateTime('now', $timezone);
        
        $event->set([
            'eventType' => LeadEventType::ASSIGNED->value,
            'eventDate' => $dt->format('Y-m-d H:i:s'),
            'description' => "{$oldCoachName} -> {$newCoachName}",
        ]);
        
        $this->entityManager->saveEntity($event);
        
        $this->entityManager->getRDBRepository('CLeadEvent')
            ->getRelation($event, 'lead')
            ->relate($person);

        $this->entityManager->saveEntity($event);
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