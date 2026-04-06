<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Custom\Enums\LeadEventType;
use Espo\Custom\Enums\LeadReasonLost;
use Espo\Custom\Enums\LeadStage;
use Espo\Custom\Enums\LeadStatus;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

readonly class LeadStatusService
{

    private const STATUS_MAP = [
        LeadEventType::ASSIGNED->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::TO_CALL->value
        ],
        LeadEventType::CALL_AGAIN->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::FOLLOW_UP->value
        ],
        LeadEventType::WRONG_NUMBER->value => [
            'status' => LeadStatus::DEAD->value,
            'cStage' => null,
            'cReasonLost' => LeadReasonLost::WRONG_NUMBER->value
        ],
        LeadEventType::NOT_INTERESTED->value => [
            'status' => LeadStatus::DEAD->value,
            'cStage' => null,
            'cReasonLost' => LeadReasonLost::NOT_INTERESTED->value
        ],
        LeadEventType::MESSAGE_TO_BE_SENT->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::MESSAGE_TO_BE_SENT->value
        ],
        LeadEventType::MESSAGE_SENT->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::FOLLOW_UP->value
        ],
        LeadEventType::BOOK_INTRO->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::INTRO_SCHEDULED->value
        ],
        LeadEventType::INTRO_NO_SHOW->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::FOLLOW_UP->value
        ],
        LeadEventType::KICKSTART_BOOKED->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::KS_PLANNED->value
        ],
        LeadEventType::BECAME_CLIENT->value => [
            'status' => LeadStatus::BECAME_CLIENT->value,
            'cStage' => LeadStage::BECAME_CLIENT->value,
        ],
        LeadEventType::BECAME_COACH->value => [
            'status' => LeadStatus::CONVERTED->value,
            'cStage' => null
        ],
        LeadEventType::NOT_CONVERTED->value => [
            'status' => LeadStatus::DEAD->value,
            'cStage' => null,
            'cReasonLost' => LeadReasonLost::NOT_CONVERTED->value
        ],
        LeadEventType::STILL_THINKING->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::KS_DOUBT->value
        ],
        LeadEventType::NO_SHOW->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::FOLLOW_UP->value
        ],
        LeadEventType::KICKSTART_NO_SHOW->value => [
            'status' => LeadStatus::ASSIGNED->value,
            'cStage' => LeadStage::FOLLOW_UP->value
        ],
    ];

    public function __construct(
        private EntityManager $entityManager,
        private LeadCallCountService $callCountService,
    ) {}

    /**
     * Update lead status based on event type
     * 
     * @param Entity $lead
     * @param LeadEventType $eventType
     * @return void
     */
    public function updateStatus(Entity $lead, LeadEventType $eventType): void
    {
        if ($eventType === LeadEventType::NO_ANSWER) {
            $this->handleNoAnswerStatus($lead);
            return;
        }

        if (isset(self::STATUS_MAP[$eventType->value])) {
            $updates = self::STATUS_MAP[$eventType->value];
            $lead->set($updates);
            $this->entityManager->saveEntity($lead);
        }
    }

    /**
     * Handle NO_ANSWER event with call count logic
     * 
     * @param Entity $lead
     * @return void
     */
    private function handleNoAnswerStatus(Entity $lead): void
    {
        $team = $this->entityManager->getRelation($lead, 'cTeam')->findOne();
        $maxCallAttempts = $team?->get('maxCallAttempts') ?? 3;
        $currentCallCount = $this->callCountService->getCount($lead->getId());

        if ($currentCallCount >= $maxCallAttempts) {
            $lead->set([
                'status' => LeadStatus::ASSIGNED->value,
                'cStage' => LeadStage::MESSAGE_TO_BE_SENT->value
            ]);
        } else {
            $lead->set([
                'status' => LeadStatus::ASSIGNED->value,
                'cStage' => LeadStage::FOLLOW_UP->value
            ]);
        }

        $this->entityManager->saveEntity($lead);
    }

    public function getStatusForEvent(LeadEventType $eventType): ?array
    {
        return self::STATUS_MAP[$eventType->value] ?? null;
    }

    public function isDeadEvent(LeadEventType $eventType): bool
    {
        $status = self::STATUS_MAP[$eventType->value] ?? null;
        return $status && $status['status'] === LeadStatus::DEAD->value;
    }

    public function isConversionEvent(LeadEventType $eventType): bool
    {
        $status = self::STATUS_MAP[$eventType->value] ?? null;
        return $status && $status['status'] === LeadStatus::CONVERTED->value;
    }
}