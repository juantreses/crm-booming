<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Custom\Enums\HomRating;
use Espo\Custom\Enums\IntroMeetingType;
use Espo\Custom\Enums\LeadStage;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

readonly class IntroMeetingService
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function canBook(Entity $lead, IntroMeetingType $meetingType): bool
    {
        if ($meetingType->hasUsageLimit()) {
            $used = $this->getUsageCount($lead, $meetingType);
            return $used < $meetingType->getMaxUsage();
        }
        
        return true;
    }

    public function recordAttendance(Entity $lead, IntroMeetingType $meetingType, ?HomRating $homRating = null): LeadStage
    {
        if ($meetingType === IntroMeetingType::SPARK) {
            $this->incrementSparkUsage($lead);
        }
        
        return $this->determineNextStage($lead, $meetingType, $homRating);
    }

    private function determineNextStage(Entity $lead, IntroMeetingType $meetingType, ?HomRating $homRating): LeadStage
    {
        if ($meetingType === IntroMeetingType::HOM && $homRating) {
            // A/B choose SPARK or Kickstart next, C/D go to a Royalty meeting
            return $homRating->leadsToRoyalty() ? LeadStage::BOOK_RI : LeadStage::INTRO_ATTENDED;
        }

        if ($meetingType === IntroMeetingType::SPARK) {
            $sparksUsed = $lead->get('cSparksUsed') ?? 0;
            
            if ($sparksUsed < $meetingType->getMaxUsage()) {
                return LeadStage::INTRO_ATTENDED;
            }

            return LeadStage::BOOK_KS;
        }
        
        return LeadStage::BOOK_KS;
    }

    public function getUsageCount(Entity $lead, IntroMeetingType $meetingType): int
    {
        if ($meetingType === IntroMeetingType::SPARK) {
            return (int) ($lead->get('cSparksUsed') ?? 0);
        }
        
        return 0;
    }

    public function getRemainingUsage(Entity $lead, IntroMeetingType $meetingType): int
    {
        if (!$meetingType->hasUsageLimit()) {
            return 999; // Unlimited
        }
        
        $used = $this->getUsageCount($lead, $meetingType);
        $max = $meetingType->getMaxUsage();
        
        return max(0, $max - $used);
    }

    private function incrementSparkUsage(Entity $lead): void
    {
        $current = (int) ($lead->get('cSparksUsed') ?? 0);
        
        if ($current >= 2) {
            throw new \RuntimeException("Lead has already used the maximum number of SPARK sessions (2)");
        }
        
        $lead->set('cSparksUsed', $current + 1);
        $this->entityManager->saveEntity($lead);
    }


    public function shouldOfferAnotherIntro(Entity $lead): bool
    {
        $introType = $lead->get('cMeetingType');
        
        if (!$introType) {
            return false;
        }
        
        $meetingType = IntroMeetingType::fromCalendarType($introType);
        
        if (!$meetingType) {
            return false;
        }
        
        return $this->canBook($lead, $meetingType);
    }

    public function getIntroMeetingType(Entity $lead): ?IntroMeetingType
    {
        $type = $lead->get('cMeetingType');
        
        if (!$type) {
            return null;
        }
        
        return IntroMeetingType::fromCalendarType($type);
    }
}
