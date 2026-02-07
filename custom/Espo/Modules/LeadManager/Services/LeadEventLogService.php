<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\Utils\DateTimeFactory;

readonly class LeadEventLogService
{
    public function __construct(
        private EntityManager $entityManager,
        private LeadStatusService $statusService,
    ) {}

    public function logEvent(
        string $leadId,
        LeadEventType $eventType,
        ?string $eventDate = null,
        ?string $description = null
    ): array {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        
        if (!$lead) {
            throw new NotFound('Lead not found');
        }

        $event = $this->createEvent($eventType, $eventDate, $description);
        $this->linkEventToLead($event, $lead);
        $this->statusService->updateStatus($lead, $eventType);

        return [
            'success' => true,
            'eventId' => $event->getId(),
            'eventType' => $eventType->value,
            'leadStatus' => $lead->get('status'),
        ];
    }

    private function createEvent(
        LeadEventType $eventType,
        ?string $eventDate,
        ?string $description
    ): Entity {
        $event = $this->entityManager->getNewEntity('CLeadEvent');
        $dt = DateTimeFactory::parseToUtc($eventDate);

        $event->set([
            'eventType' => $eventType->value,
            'eventDate' => DateTimeFactory::formatUtc($dt),
            'description' => $description,
        ]);

        $this->entityManager->saveEntity($event);
        return $event;
    }

    private function linkEventToLead(Entity $event, Entity $lead): void
    {
        $eventRepository = $this->entityManager->getRDBRepository('CLeadEvent');
        $eventRepository->getRelation($event, 'lead')->relate($lead);
        $this->entityManager->saveEntity($event);
    }
}