<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Custom\Enums\LeadEventType;

readonly class LeadStatusService
{
    private const STATUS_MAP = [
        LeadEventType::ASSIGNED->value => LeadEventType::ASSIGNED->value,
        LeadEventType::NO_ANSWER->value => LeadEventType::CALL_AGAIN->value,
        LeadEventType::CALL_AGAIN->value => LeadEventType::CALL_AGAIN->value,
        LeadEventType::WRONG_NUMBER->value => LeadEventType::WRONG_NUMBER->value,
        LeadEventType::NOT_INTERESTED->value => LeadEventType::NOT_INTERESTED->value,
        LeadEventType::INVITED->value => LeadEventType::INVITED->value,
        LeadEventType::APPOINTMENT_BOOKED->value => LeadEventType::APPOINTMENT_BOOKED->value,
        LeadEventType::ATTENDED->value => LeadEventType::ATTENDED->value,
        LeadEventType::APPOINTMENT_CANCELLED->value => LeadEventType::APPOINTMENT_CANCELLED->value,
        LeadEventType::BECAME_CLIENT->value => LeadEventType::BECAME_CLIENT->value,
        LeadEventType::NOT_CONVERTED->value => LeadEventType::NOT_CONVERTED->value,
        LeadEventType::STILL_THINKING->value => LeadEventType::STILL_THINKING->value,
        LeadEventType::NO_SHOW->value => LeadEventType::NO_SHOW->value,
        LeadEventType::BECAME_COACH->value => LeadEventType::BECAME_COACH->value,
        LeadEventType::MESSAGE_TO_BE_SENT->value => LeadEventType::MESSAGE_TO_BE_SENT->value,
        LeadEventType::MESSAGE_SENT->value => LeadEventType::MESSAGE_SENT->value,
    ];

    public function __construct(
        private EntityManager $entityManager,
        private LeadCallCountService $callCountService,
    ) {}

    public function updateStatus(Entity $lead, LeadEventType $eventType): void
    {
        if ($eventType->value === LeadEventType::NO_ANSWER->value) {
            $this->handleNoAnswerStatus($lead);
            return;
        }

        if (isset(self::STATUS_MAP[$eventType->value])) {
            $lead->set('status', self::STATUS_MAP[$eventType->value]);
            $this->entityManager->saveEntity($lead);
        }
    }

    private function handleNoAnswerStatus(Entity $lead): void
    {
        $team = $this->entityManager->getRelation($lead, 'cTeam')->findOne();
        $maxCallAttempts = $team?->get('maxCallAttempts') ?? 3;
        $currentCallCount = $this->callCountService->getCount($lead->getId());

        if ($currentCallCount >= $maxCallAttempts) {
            $lead->set('status', LeadEventType::MESSAGE_TO_BE_SENT->value);
        } else {
            $lead->set('status', LeadEventType::CALL_AGAIN->value);
        }

        $this->entityManager->saveEntity($lead);
    }
}