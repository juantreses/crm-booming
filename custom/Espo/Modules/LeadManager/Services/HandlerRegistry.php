<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Modules\LeadManager\Handlers\Call\CalledHandler;
use Espo\Modules\LeadManager\Handlers\Call\InvitedHandler;
use Espo\Modules\LeadManager\Handlers\Call\CallAgainHandler;
use Espo\Modules\LeadManager\Handlers\Call\NoAnswerHandler;
use Espo\Modules\LeadManager\Handlers\Call\WrongNumberHandler;
use Espo\Modules\LeadManager\Handlers\Call\NotInterestedHandler;
use Espo\Modules\LeadManager\Handlers\Kickstart\BecameClientHandler;
use Espo\Modules\LeadManager\Handlers\Kickstart\BookedHandler;
use Espo\Modules\LeadManager\Handlers\Kickstart\NoShowHandler;
use Espo\Modules\LeadManager\Handlers\Kickstart\NotConvertedHandler;
use Espo\Modules\LeadManager\Handlers\Kickstart\StillThinkingHandler;
use Espo\Modules\LeadManager\Handlers\Kickstart\CancelledHandler;
use Espo\Modules\LeadManager\Handlers\KickstartFollowUp\BecameClientFollowUpHandler;
use Espo\Modules\LeadManager\Handlers\KickstartFollowUp\NotConvertedFollowUpHandler;
use Espo\Modules\LeadManager\Handlers\IntroMeeting\AttendanceHandler;
use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\IntroMeetingOutcome;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\MessageSentOutcome;
use Espo\Modules\LeadManager\Handlers\IntroMeeting;
use Espo\Modules\LeadManager\Handlers\Message;
use Espo\Modules\LeadManager\Handlers\OutcomeHandler;
use Espo\Modules\LeadManager\Handlers\Royalty;
use Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;

readonly class HandlerRegistry
{
    private array $callHandlers;
    private array $kickstartHandlers;
    private array $messageHandlers;
    private array $kickstartFollowUpHandlers;
    private array $introMeetingHandlers;
    private array $royaltyHandlers;
    private array $royaltyFollowUpHandlers;

    public function __construct(
        CalledHandler $calledHandler,
        InvitedHandler $callInvitedHandler,
        CallAgainHandler $callAgainHandler,
        NoAnswerHandler $noAnswerHandler,
        WrongNumberHandler $wrongNumberHandler,
        NotInterestedHandler $callNotInterestedHandler,
        BecameClientHandler $becameClientHandler,
        private BookedHandler $kickstartBookingHandler,
        NoShowHandler $noShowHandler,
        NotConvertedHandler $notConvertedHandler,
        StillThinkingHandler $stillThinkingHandler,
        CancelledHandler $cancelledHandler,
        BecameClientFollowUpHandler $becameClientFollowUpHandler,
        NotConvertedFollowUpHandler $notConvertedFollowUpHandler,
        Message\InvitedHandler $messageInvitedHandler,
        Message\NotInterestedHandler $messageNotInterestedHandler,
        Message\CallAgainHandler $messageCallAgainHandler,
        AttendanceHandler $introAttendanceHandler,
        IntroMeeting\NoShowHandler $introNoShowHandler,
        IntroMeeting\CancelledHandler $introCancelledHandler,
        Royalty\BecameClientHandler $royaltyBecameClientHandler,
        private Royalty\BookedHandler $royaltyBookingHandler,
        Royalty\NoShowHandler $royaltyNoShowHandler,
        Royalty\NotConvertedHandler $royaltyNotConvertedHandler,
        Royalty\StillThinkingHandler $royaltyStillThinkingHandler,
        Royalty\CancelledHandler $royaltyCancelledHandler,
        RoyaltyFollowUp\BecameClientFollowUpHandler $royaltyBecameClientFollowUpHandler,
        RoyaltyFollowUp\NotConvertedFollowUpHandler $royaltyNotConvertedFollowUpHandler,
    ) {
        $this->callHandlers = [
            CallOutcome::CALLED->value => $calledHandler,
            CallOutcome::INVITED->value => $callInvitedHandler,
            CallOutcome::CALL_AGAIN->value => $callAgainHandler,
            CallOutcome::NO_ANSWER->value => $noAnswerHandler,
            CallOutcome::WRONG_NUMBER->value => $wrongNumberHandler,
            CallOutcome::NOT_INTERESTED->value => $callNotInterestedHandler,
        ];

        $this->kickstartHandlers = [
            KickstartOutcome::BECAME_CLIENT->value => $becameClientHandler,
            KickstartOutcome::NO_SHOW->value => $noShowHandler,
            KickstartOutcome::NOT_CONVERTED->value => $notConvertedHandler,
            KickstartOutcome::STILL_THINKING->value => $stillThinkingHandler,
            KickstartOutcome::CANCELLED->value => $cancelledHandler,
        ];

        $this->messageHandlers = [
            MessageSentOutcome::INVITED->value => $messageInvitedHandler,
            MessageSentOutcome::NOT_INTERESTED->value => $messageNotInterestedHandler,
            MessageSentOutcome::CALL_AGAIN->value => $messageCallAgainHandler,
        ];

        $this->kickstartFollowUpHandlers = [
            KickstartOutcome::BECAME_CLIENT->value => $becameClientFollowUpHandler,
            KickstartOutcome::NOT_CONVERTED->value => $notConvertedFollowUpHandler,
        ];

        $this->introMeetingHandlers = [
            IntroMeetingOutcome::ATTENDED->value => $introAttendanceHandler,
            IntroMeetingOutcome::NO_SHOW->value => $introNoShowHandler,
            IntroMeetingOutcome::CANCELLED->value => $introCancelledHandler,
        ];

        $this->royaltyHandlers = [
            KickstartOutcome::BECAME_CLIENT->value => $royaltyBecameClientHandler,
            KickstartOutcome::NO_SHOW->value => $royaltyNoShowHandler,
            KickstartOutcome::NOT_CONVERTED->value => $royaltyNotConvertedHandler,
            KickstartOutcome::STILL_THINKING->value => $royaltyStillThinkingHandler,
            KickstartOutcome::CANCELLED->value => $royaltyCancelledHandler,
        ];

        $this->royaltyFollowUpHandlers = [
            KickstartOutcome::BECAME_CLIENT->value => $royaltyBecameClientFollowUpHandler,
            KickstartOutcome::NOT_CONVERTED->value => $royaltyNotConvertedFollowUpHandler,
        ];
    }

    public function getCallHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->callHandlers[$outcome])) {
            throw new BadRequest("Invalid call outcome: $outcome");
        }

        return $this->callHandlers[$outcome];
    }

    public function getKickstartHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->kickstartHandlers[$outcome])) {
            throw new BadRequest("Invalid kickstart outcome: $outcome");
        }

        return $this->kickstartHandlers[$outcome];
    }

    public function getKickstartBookingHandler(): OutcomeHandler
    {
        return $this->kickstartBookingHandler;
    }

    public function getKickstartFollowUpHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->kickstartFollowUpHandlers[$outcome])) {
            throw new BadRequest("Invalid kickstart follow-up outcome: $outcome");
        }

        return $this->kickstartFollowUpHandlers[$outcome];
    }

    public function getRoyaltyHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->royaltyHandlers[$outcome])) {
            throw new BadRequest("Invalid royalty outcome: $outcome");
        }

        return $this->royaltyHandlers[$outcome];
    }

    public function getRoyaltyBookingHandler(): OutcomeHandler
    {
        return $this->royaltyBookingHandler;
    }

    public function getRoyaltyFollowUpHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->royaltyFollowUpHandlers[$outcome])) {
            throw new BadRequest("Invalid royalty follow-up outcome: $outcome");
        }

        return $this->royaltyFollowUpHandlers[$outcome];
    }

    public function getMessageHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->messageHandlers[$outcome])) {
            throw new BadRequest("Invalid message outcome: $outcome");
        }

        return $this->messageHandlers[$outcome];
    }

    public function getIntroMeetingHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->introMeetingHandlers[$outcome])) {
            throw new BadRequest("Invalid intro meeting outcome: $outcome");
        }

        return $this->introMeetingHandlers[$outcome];
    }
}
