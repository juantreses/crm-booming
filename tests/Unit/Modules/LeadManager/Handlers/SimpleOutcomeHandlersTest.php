<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Handlers;

use Espo\Modules\LeadManager\Handlers\Call;
use Espo\Modules\LeadManager\Handlers\Kickstart;
use Espo\Modules\LeadManager\Handlers\KickstartFollowUp;
use Espo\Modules\LeadManager\Handlers\Message;
use Espo\Modules\LeadManager\Handlers\OutcomeHandler;
use Espo\Modules\LeadManager\Handlers\Royalty;
use Espo\Modules\LeadManager\Handlers\RoyaltyFollowUp;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Handlers that only log events, add a note, set or clear the follow-up and update the planned meeting.
 */
final class SimpleOutcomeHandlersTest extends HandlerTestCase
{
    private const DATE = '2026-01-10 10:00:00';
    private const CALL_AGAIN = '2026-01-12 18:00:00';

    /**
     * class => [events, notes, follow-ups, meetings] for context
     * ['eventDate' => DATE, 'coachNote' => 'Notitie', 'callAgainDateTime' => CALL_AGAIN].
     *
     * @return iterable<string, array{class-string<OutcomeHandler>, list<string>, list<string>, list<string>, list<string>}>
     */
    public static function handlers(): iterable
    {
        $at = '@' . self::DATE;
        $callAgain = '@' . self::CALL_AGAIN;

        yield 'call: called' => [Call\CalledHandler::class, ["called$at"], ['Telefoon: Notitie'], ['clear'], []];
        yield 'call: call again' => [
            Call\CallAgainHandler::class, ["called$at", "call_again$at"], ['Telefoon: Notitie'], ["add:Opnieuw bellen$callAgain"], [],
        ];
        yield 'call: no answer' => [Call\NoAnswerHandler::class, ["called$at", "no_answer$at"], ['Telefoon: Notitie'], ['clear'], []];
        yield 'call: wrong number' => [Call\WrongNumberHandler::class, ["called$at", "wrong_number$at"], ['Telefoon: Notitie'], ['clear'], []];
        yield 'call: not interested' => [
            Call\NotInterestedHandler::class, ["called$at", "not_interested$at"], ['Telefoon: Notitie'], ['clear'], [],
        ];

        // Message handlers ignore the event date.
        yield 'message: call again' => [Message\CallAgainHandler::class, ['call_again@-'], ['Bericht: Notitie'], ["add:Opnieuw bellen$callAgain"], []];
        yield 'message: not interested' => [Message\NotInterestedHandler::class, ['not_interested@-'], ['Bericht: Notitie'], ['clear'], []];

        yield 'kickstart: became client' => [
            Kickstart\BecameClientHandler::class, ["attended$at", "became_client$at"], ['Kickstart: Notitie'], ['clear'], ['held:meeting1'],
        ];
        yield 'kickstart: not converted' => [
            Kickstart\NotConvertedHandler::class, ["attended$at", "not_converted$at"], ['Kickstart: Notitie'], ['clear'], ['held:meeting1'],
        ];
        yield 'kickstart: still thinking' => [
            Kickstart\StillThinkingHandler::class,
            ["attended$at", "still_thinking$at"],
            ['Kickstart: Notitie'],
            ["add:KS twijfel - Opvolging$callAgain"],
            ['held:meeting1'],
        ];
        yield 'kickstart: no show' => [
            Kickstart\NoShowHandler::class,
            ["no_show$at", "call_again$at"],
            ['Kickstart: Notitie'],
            ["add:Niet opgedaagd Kickstart$callAgain"],
            ['notHeld:meeting1'],
        ];

        yield 'royalty: became client' => [
            Royalty\BecameClientHandler::class, ["attended$at", "became_client$at"], ['Royalty: Notitie'], ['clear'], ['held:meeting1'],
        ];
        yield 'royalty: not converted' => [
            Royalty\NotConvertedHandler::class, ["attended$at", "not_converted$at"], ['Royalty: Notitie'], ['clear'], ['held:meeting1'],
        ];
        yield 'royalty: still thinking' => [
            Royalty\StillThinkingHandler::class,
            ["attended$at", "royalty_still_thinking$at"],
            ['Royalty: Notitie'],
            ["add:RI twijfel - Opvolging$callAgain"],
            ['held:meeting1'],
        ];
        yield 'royalty: no show' => [
            Royalty\NoShowHandler::class,
            ["no_show$at", "call_again$at"],
            ['Royalty: Notitie'],
            ["add:Niet opgedaagd RI$callAgain"],
            ['notHeld:meeting1'],
        ];

        yield 'kickstart follow-up: became client' => [
            KickstartFollowUp\BecameClientFollowUpHandler::class, ["became_client$at"], ['KS - Opvolging: Notitie'], ['clear'], [],
        ];
        yield 'kickstart follow-up: not converted' => [
            KickstartFollowUp\NotConvertedFollowUpHandler::class, ["not_converted$at"], ['KS - Opvolging: Notitie'], ['clear'], [],
        ];
        yield 'royalty follow-up: became client' => [
            RoyaltyFollowUp\BecameClientFollowUpHandler::class, ["became_client$at"], ['RI - Opvolging: Notitie'], ['clear'], [],
        ];
        yield 'royalty follow-up: not converted' => [
            RoyaltyFollowUp\NotConvertedFollowUpHandler::class, ["not_converted$at"], ['RI - Opvolging: Notitie'], ['clear'], [],
        ];
    }

    /**
     * @param class-string<OutcomeHandler> $class
     * @param list<string> $events
     * @param list<string> $notes
     * @param list<string> $followUps
     * @param list<string> $meetings
     */
    #[DataProvider('handlers')]
    public function testHandle(string $class, array $events, array $notes, array $followUps, array $meetings): void
    {
        $this->lead();

        $result = $this->handler($class)->handle('lead1', [
            'eventDate' => self::DATE,
            'coachNote' => 'Notitie',
            'callAgainDateTime' => self::CALL_AGAIN,
        ]);

        self::assertSame($events, $this->events);
        self::assertSame($notes, $this->notes);
        self::assertSame($followUps, $this->followUps);
        self::assertSame($meetings, $this->meetings);
        self::assertSame(
            ['success' => true, 'eventIds' => array_map(static fn (int $i) => 'ev' . ($i + 1), array_keys($events))],
            $result->toArray(),
        );
    }

    /**
     * @return iterable<string, array{class-string<OutcomeHandler>}>
     */
    public static function handlersWithFollowUpDate(): iterable
    {
        yield 'call: call again' => [Call\CallAgainHandler::class];
        yield 'message: call again' => [Message\CallAgainHandler::class];
        yield 'kickstart: still thinking' => [Kickstart\StillThinkingHandler::class];
        yield 'kickstart: no show' => [Kickstart\NoShowHandler::class];
        yield 'royalty: no show' => [Royalty\NoShowHandler::class];
    }

    /**
     * @param class-string<OutcomeHandler> $class
     */
    #[DataProvider('handlersWithFollowUpDate')]
    public function testWithoutCallAgainDateTheFollowUpIsCleared(string $class): void
    {
        $this->lead();

        $this->handler($class)->handle('lead1', []);

        self::assertSame(['clear'], $this->followUps);
    }

    public function testWithoutCoachNoteNoNoteIsAdded(): void
    {
        $this->lead();

        $this->handler(Call\CalledHandler::class)->handle('lead1', ['coachNote' => '']);

        self::assertSame([], $this->notes);
        self::assertSame(['called@-'], $this->events);
    }

    public function testWithoutPlannedMeetingNothingIsMarked(): void
    {
        $this->lead();
        $this->plannedMeeting = null;

        $this->handler(Kickstart\BecameClientHandler::class)->handle('lead1', []);

        self::assertSame(['held:none'], $this->meetings);
    }
}
