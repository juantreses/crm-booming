<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Enums\CallOutcome;
use Espo\Custom\Enums\KickstartOutcome;
use Espo\Custom\Enums\MessageSentOutcome;
use Espo\Modules\LeadManager\Handlers\OutcomeHandler;
use Espo\Modules\LeadManager\Handlers\Call;
use Espo\Modules\LeadManager\Handlers\Kickstart;
use Espo\Modules\LeadManager\Handlers\KickstartFollowUp;
use Espo\Modules\LeadManager\Handlers\Message;

readonly class HandlerRegistry
{
    private array $callHandlers;
    private array $kickstartHandlers;
    private array $messageHandlers;
    private array $kickstartFollowUpHandlers;

    public function __construct(
        Call\CalledHandler $calledHandler,
        Call\InvitedHandler $callInvitedHandler,
        Call\CallAgainHandler $callAgainHandler,
        Call\NoAnswerHandler $noAnswerHandler,
        Call\WrongNumberHandler $wrongNumberHandler,
        Call\NotInterestedHandler $callNotInterestedHandler,
        Kickstart\BecameClientHandler $becameClientHandler,
        Kickstart\NoShowHandler $noShowHandler,
        Kickstart\NotConvertedHandler $notConvertedHandler,
        Kickstart\StillThinkingHandler $stillThinkingHandler,
        Kickstart\CancelledHandler $cancelledHandler,
        KickStartFollowUp\BecameClientFollowUpHandler $becameClientFollowUpHandler,
        KickstartFollowUp\NotConvertedFollowUpHandler $notConvertedFollowUpHandler,
        Message\InvitedHandler $messageInvitedHandler,
        Message\NotInterestedHandler $messageNotInterestedHandler,
        Message\CallAgainHandler $messageCallAgainHandler,
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

    public function getKickstartFollowUpHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->kickstartFollowUpHandlers[$outcome])) {
            throw new BadRequest("Invalid kickstart follow-up outcome: $outcome");
        }

        return $this->kickstartFollowUpHandlers[$outcome];
    }

    public function getMessageHandler(string $outcome): OutcomeHandler
    {
        if (!isset($this->messageHandlers[$outcome])) {
            throw new BadRequest("Invalid message outcome: $outcome");
        }

        return $this->messageHandlers[$outcome];
    }
}