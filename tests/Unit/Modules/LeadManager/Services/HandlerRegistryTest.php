<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\LeadManager\Handlers\Call;
use Espo\Modules\LeadManager\Handlers\IntroMeeting;
use Espo\Modules\LeadManager\Handlers\Kickstart;
use Espo\Modules\LeadManager\Handlers\KickstartFollowUp;
use Espo\Modules\LeadManager\Handlers\Message;
use Espo\Modules\LeadManager\Handlers\Royalty;
use Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;
use Espo\Modules\LeadManager\Services\HandlerRegistry;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class HandlerRegistryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, class-string}>
     */
    public static function mapping(): iterable
    {
        yield 'call called' => ['getCallHandler', 'called', Call\CalledHandler::class];
        yield 'call invited' => ['getCallHandler', 'invited', Call\InvitedHandler::class];
        yield 'call call again' => ['getCallHandler', 'call_again', Call\CallAgainHandler::class];
        yield 'call no answer' => ['getCallHandler', 'no_answer', Call\NoAnswerHandler::class];
        yield 'call wrong number' => ['getCallHandler', 'wrong_number', Call\WrongNumberHandler::class];
        yield 'call not interested' => ['getCallHandler', 'not_interested', Call\NotInterestedHandler::class];

        yield 'kickstart became client' => ['getKickstartHandler', 'became_client', Kickstart\BecameClientHandler::class];
        yield 'kickstart no show' => ['getKickstartHandler', 'no_show', Kickstart\NoShowHandler::class];
        yield 'kickstart not converted' => ['getKickstartHandler', 'not_converted', Kickstart\NotConvertedHandler::class];
        yield 'kickstart still thinking' => ['getKickstartHandler', 'still_thinking', Kickstart\StillThinkingHandler::class];
        yield 'kickstart cancelled' => ['getKickstartHandler', 'cancelled', Kickstart\CancelledHandler::class];
        yield 'kickstart booking' => ['getKickstartBookingHandler', null, Kickstart\BookedHandler::class];
        yield 'kickstart follow-up became client' => [
            'getKickstartFollowUpHandler', 'became_client', KickstartFollowUp\BecameClientFollowUpHandler::class,
        ];
        yield 'kickstart follow-up not converted' => [
            'getKickstartFollowUpHandler', 'not_converted', KickstartFollowUp\NotConvertedFollowUpHandler::class,
        ];

        yield 'royalty became client' => ['getRoyaltyHandler', 'became_client', Royalty\BecameClientHandler::class];
        yield 'royalty no show' => ['getRoyaltyHandler', 'no_show', Royalty\NoShowHandler::class];
        yield 'royalty not converted' => ['getRoyaltyHandler', 'not_converted', Royalty\NotConvertedHandler::class];
        yield 'royalty still thinking' => ['getRoyaltyHandler', 'still_thinking', Royalty\StillThinkingHandler::class];
        yield 'royalty cancelled' => ['getRoyaltyHandler', 'cancelled', Royalty\CancelledHandler::class];
        yield 'royalty booking' => ['getRoyaltyBookingHandler', null, Royalty\BookedHandler::class];
        yield 'royalty follow-up became client' => [
            'getRoyaltyFollowUpHandler', 'became_client', RoyaltyFollowUp\BecameClientFollowUpHandler::class,
        ];
        yield 'royalty follow-up not converted' => [
            'getRoyaltyFollowUpHandler', 'not_converted', RoyaltyFollowUp\NotConvertedFollowUpHandler::class,
        ];

        yield 'message invited' => ['getMessageHandler', 'invited', Message\InvitedHandler::class];
        yield 'message not interested' => ['getMessageHandler', 'not_interested', Message\NotInterestedHandler::class];
        yield 'message call again' => ['getMessageHandler', 'call_again', Message\CallAgainHandler::class];

        yield 'intro attended' => ['getIntroMeetingHandler', 'attended', IntroMeeting\AttendanceHandler::class];
        yield 'intro no show' => ['getIntroMeetingHandler', 'no_show', IntroMeeting\NoShowHandler::class];
        yield 'intro cancelled' => ['getIntroMeetingHandler', 'cancelled', IntroMeeting\CancelledHandler::class];
    }

    /**
     * @param class-string $expected
     */
    #[DataProvider('mapping')]
    public function testReturnsHandlerForOutcome(string $method, ?string $outcome, string $expected): void
    {
        $handler = $outcome === null ? $this->registry()->$method() : $this->registry()->$method($outcome);

        // Royalty handlers extend the Kickstart ones, so compare the exact class.
        self::assertSame($expected, get_parent_class($handler));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidOutcomes(): iterable
    {
        yield 'call' => ['getCallHandler', 'cancelled', 'Invalid call outcome: cancelled'];
        yield 'kickstart' => ['getKickstartHandler', 'called', 'Invalid kickstart outcome: called'];
        yield 'kickstart follow-up' => ['getKickstartFollowUpHandler', 'no_show', 'Invalid kickstart follow-up outcome: no_show'];
        yield 'royalty' => ['getRoyaltyHandler', 'called', 'Invalid royalty outcome: called'];
        yield 'royalty follow-up' => ['getRoyaltyFollowUpHandler', 'still_thinking', 'Invalid royalty follow-up outcome: still_thinking'];
        yield 'message' => ['getMessageHandler', 'no_answer', 'Invalid message outcome: no_answer'];
        yield 'intro meeting' => ['getIntroMeetingHandler', 'became_client', 'Invalid intro meeting outcome: became_client'];
    }

    #[DataProvider('invalidOutcomes')]
    public function testRejectsUnknownOutcome(string $method, string $outcome, string $message): void
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage($message);

        $this->registry()->$method($outcome);
    }

    /**
     * Builds the registry with one stub per constructor parameter. A stub's class is a generated
     * subclass of the requested handler, so get_parent_class() gives the handler class.
     */
    private function registry(): HandlerRegistry
    {
        $arguments = [];

        foreach ((new ReflectionClass(HandlerRegistry::class))->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType) {
                throw new LogicException("Unexpected type for \${$parameter->getName()}");
            }

            $arguments[] = $this->createStub($type->getName());
        }

        return (new ReflectionClass(HandlerRegistry::class))->newInstanceArgs($arguments);
    }
}
