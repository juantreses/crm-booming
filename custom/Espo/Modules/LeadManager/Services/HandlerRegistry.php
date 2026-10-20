<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\IntroMeetingOutcome;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\MessageSentOutcome;
use Espo\Modules\LeadManager\Handlers\OutcomeHandler;
use Espo\Modules\LeadManager\Handlers\Call;
use Espo\Modules\LeadManager\Handlers\IntroMeeting;
use Espo\Modules\LeadManager\Handlers\Kickstart;
use Espo\Modules\LeadManager\Handlers\KickstartFollowUp;
use Espo\Modules\LeadManager\Handlers\Message;
use Espo\Modules\LeadManager\Handlers\Royalty;
use Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;

readonly class HandlerRegistry
{
    private array $callHandlers;
    private array $kickstartHandlers;
    private array $messageHandlers;
    private array $kickstartFollowUpHandlers;
    private array $introMeetingHandlers;
    private Kickstart\BookedHandler $kickstartBookingHandler;
    private array $royaltyHandlers;
    private array $royaltyFollowUpHandlers;
    private Royalty\BookedHandler $royaltyBookingHandler;

    public function __construct(
        Call\CalledHandler $calledHandler,
        Call\InvitedHandler $callInvitedHandler,
        Call\CallAgainHandler $callAgainHandler,
        Call\NoAnswerHandler $noAnswerHandler,
        Call\WrongNumberHandler $wrongNumberHandler,
        Call\NotInterestedHandler $callNotInterestedHandler,
        Kickstart\BecameClientHandler $becameClientHandler,
        Kickstart\BookedHandler $kickstartBookedHandler,
        Kickstart\NoShowHandler $noShowHandler,
        Kickstart\NotConvertedHandler $notConvertedHandler,
        Kickstart\StillThinkingHandler $stillThinkingHandler,
        Kickstart\CancelledHandler $cancelledHandler,
        KickStartFollowUp\BecameClientFollowUpHandler $becameClientFollowUpHandler,
        KickstartFollowUp\NotConvertedFollowUpHandler $notConvertedFollowUpHandler,
        Message\InvitedHandler $messageInvitedHandler,
        Message\NotInterestedHandler $messageNotInterestedHandler,
        Message\CallAgainHandler $messageCallAgainHandler,
        IntroMeeting\AttendanceHandler $introAttendanceHandler,
        IntroMeeting\NoShowHandler $introNoShowHandler,
        IntroMeeting\CancelledHandler $introCancelledHandler,
        Royalty\BecameClientHandler $royaltyBecameClientHandler,
        Royalty\BookedHandler $royaltyBookedHandler,
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
        $this->kickstartBookingHandler = $kickstartBookedHandler;

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
        $this->royaltyBookingHandler = $royaltyBookedHandler;

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
