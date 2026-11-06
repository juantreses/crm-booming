<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Services;

use Closure;
use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Custom\Enums\HomRating;
use Espo\Custom\Enums\LeadEventType;
use Espo\Entities\User;
use Espo\Modules\LeadManager\Handlers\OutcomeHandler;
use Espo\Modules\LeadManager\Services\HandlerRegistry;
use Espo\Modules\LeadManager\Services\LeadCallCountService;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadEventService;
use Espo\Modules\LeadManager\ValueObjects\OutcomeResult;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class LeadEventServiceTest extends TestCase
{
    use InMemoryEntityManager;

    /** @var list<string> registry method + outcome per lookup */
    private array $lookups = [];

    /** @var list<array{string, array<string, mixed>}> */
    private array $handled = [];

    /** @var list<string> */
    private array $callCount = [];

    /** @var list<array{string, LeadEventType, ?string, ?string}> */
    private array $loggedEvents = [];

    private mixed $originalLog = null;

    protected function setUp(): void
    {
        $this->originalLog = $GLOBALS['log'] ?? null;
        $GLOBALS['log'] = $this->createStub(Log::class);
    }

    protected function tearDown(): void
    {
        $GLOBALS['log'] = $this->originalLog;
    }

    public function testLogCallIncrementsCountAndPassesContext(): void
    {
        $result = $this->service()->logCall((object) [
            'id' => 'lead1',
            'outcome' => 'invited',
            'callDateTime' => '2026-01-10 10:00:00',
            'callAgainDateTime' => null,
            'coachNote' => 'Notitie',
            'calendarId' => 'agenda',
            'selectedDate' => '2026-01-20',
            'selectedTime' => '19:00',
            'ignored' => 'x',
        ]);

        self::assertSame(['increment:lead1'], $this->callCount);
        self::assertSame(['getCallHandler:invited'], $this->lookups);
        self::assertSame([['lead1', [
            'eventDate' => '2026-01-10 10:00:00',
            'callAgainDateTime' => null,
            'coachNote' => 'Notitie',
            'calendarId' => 'agenda',
            'selectedDate' => '2026-01-20',
            'selectedTime' => '19:00',
        ]]], $this->handled);
        self::assertSame(['success' => true, 'eventIds' => ['ev1']], $result);
    }

    public function testLogKickstartAndRoyaltyPassTheSameContext(): void
    {
        $data = (object) [
            'id' => 'lead1',
            'outcome' => 'cancelled',
            'kickstartDateTime' => '2026-01-10 10:00:00',
            'callAgainDateTime' => '2026-01-12 18:00:00',
            'coachNote' => 'Notitie',
            'cancellationAction' => 'reschedule_later',
        ];
        $expectedContext = [
            'eventDate' => '2026-01-10 10:00:00',
            'callAgainDateTime' => '2026-01-12 18:00:00',
            'coachNote' => 'Notitie',
            'cancellationAction' => 'reschedule_later',
            'calendarId' => null,
            'selectedDate' => null,
            'selectedTime' => null,
        ];

        $service = $this->service();
        $service->logKickstart($data);
        $service->logRoyalty($data);

        self::assertSame(['getKickstartHandler:cancelled', 'getRoyaltyHandler:cancelled'], $this->lookups);
        self::assertSame([['lead1', $expectedContext], ['lead1', $expectedContext]], $this->handled);
        self::assertSame([], $this->callCount);
    }

    public function testFollowUps(): void
    {
        $data = (object) ['id' => 'lead1', 'outcome' => 'not_converted', 'followUpDateTime' => '2026-01-10 10:00:00', 'coachNote' => 'N'];

        $service = $this->service();
        $service->logKickstartFollowUp($data);
        $service->logRoyaltyFollowUp($data);

        self::assertSame(['getKickstartFollowUpHandler:not_converted', 'getRoyaltyFollowUpHandler:not_converted'], $this->lookups);
        self::assertSame(
            [['lead1', ['eventDate' => '2026-01-10 10:00:00', 'coachNote' => 'N']], ['lead1', ['eventDate' => '2026-01-10 10:00:00', 'coachNote' => 'N']]],
            $this->handled,
        );
    }

    public function testBookings(): void
    {
        $service = $this->service();
        $service->bookKickstart((object) ['id' => 'lead1', 'calendarId' => 'agenda', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00']);
        $service->bookRoyalty((object) ['id' => 'lead1', 'eventDate' => '2026-01-10 10:00:00', 'coachNote' => 'N']);

        self::assertSame(['getKickstartBookingHandler', 'getRoyaltyBookingHandler'], $this->lookups);
        self::assertSame([
            ['lead1', ['eventDate' => null, 'coachNote' => null, 'calendarId' => 'agenda', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00']],
            ['lead1', ['eventDate' => '2026-01-10 10:00:00', 'coachNote' => 'N', 'calendarId' => null, 'selectedDate' => null, 'selectedTime' => null]],
        ], $this->handled);
    }

    public function testLogIntroMeetingPassesHomRatingAndNextBooking(): void
    {
        $this->service()->logIntroMeeting((object) [
            'id' => 'lead1',
            'outcome' => 'attended',
            'introDateTime' => '2026-01-10 10:00:00',
            'homRating' => 'C',
            'nextBooking' => (object) ['calendarId' => 'ri', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00'],
        ]);

        self::assertSame(['getIntroMeetingHandler:attended'], $this->lookups);
        self::assertSame([['lead1', [
            'eventDate' => '2026-01-10 10:00:00',
            'callAgainDateTime' => null,
            'coachNote' => null,
            'cancellationAction' => null,
            'calendarId' => null,
            'selectedDate' => null,
            'selectedTime' => null,
            'homRating' => HomRating::C,
            'nextBooking' => ['calendarId' => 'ri', 'selectedDate' => '2026-01-20', 'selectedTime' => '19:00'],
        ]]], $this->handled);
    }

    public function testLogIntroMeetingWithoutNextBookingHasNoNextBookingKey(): void
    {
        $this->service()->logIntroMeeting((object) ['id' => 'lead1', 'outcome' => 'no_show']);

        self::assertArrayNotHasKey('nextBooking', $this->handled[0][1]);
        self::assertNull($this->handled[0][1]['homRating']);
    }

    public function testLogMessageOutcome(): void
    {
        $this->service()->logMessageOutcome((object) ['id' => 'lead1', 'outcome' => 'call_again', 'callAgainDateTime' => '2026-01-12 18:00:00']);

        self::assertSame(['getMessageHandler:call_again'], $this->lookups);
        self::assertSame([['lead1', [
            'callAgainDateTime' => '2026-01-12 18:00:00',
            'coachNote' => null,
            'calendarId' => null,
            'selectedDate' => null,
            'selectedTime' => null,
        ]]], $this->handled);
    }

    public function testLogMessageSentLogsEventDirectly(): void
    {
        $result = $this->service()->logMessageSent((object) ['id' => 'lead1']);

        self::assertSame([['lead1', LeadEventType::MESSAGE_SENT, null, null]], $this->loggedEvents);
        self::assertSame([], $this->lookups);
        self::assertSame('ev-logged', $result['eventId']);
    }

    public function testResetLead(): void
    {
        $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'status' => 'Dead', 'cStage' => null, 'cCallCount' => 4]));

        $this->service(userName: 'Jan Coach')->resetLead((object) ['id' => 'lead1']);

        self::assertSame(['reset:lead1'], $this->callCount);
        self::assertCount(1, $this->loggedEvents);
        [$leadId, $type, $date, $description] = $this->loggedEvents[0];
        self::assertSame('lead1', $leadId);
        self::assertSame(LeadEventType::RESET, $type);
        self::assertNull($date);
        self::assertSame(
            "RESET door Jan Coach\nLead teruggezet naar status \"[status:Assigned]\" en fase \"[cStage:to_call]\".\n"
            . "Vorige status: [status:Dead]\nVorige fase: -\nBelpogingen teruggezet: 4 → 0",
            $description,
        );
    }

    public function testResetLeadFallsBackToUserName(): void
    {
        $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'status' => 'Assigned']));

        $this->service(userName: '')->resetLead((object) ['id' => 'lead1']);

        self::assertStringStartsWith("RESET door jan\n", $this->loggedEvents[0][3]);
    }

    public function testConvertedLeadCannotBeReset(): void
    {
        $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'status' => 'Converted']));

        $this->expectException(Forbidden::class);
        $this->expectExceptionMessage('Een geconverteerde lead kan niet gereset worden.');

        try {
            $this->service()->resetLead((object) ['id' => 'lead1']);
        } finally {
            self::assertSame([], $this->callCount);
        }
    }

    public function testResetRequiresEditAccess(): void
    {
        $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'status' => 'Assigned']));

        $this->expectException(Forbidden::class);

        $this->service(canEdit: false)->resetLead((object) ['id' => 'lead1']);
    }

    public function testResetUnknownLead(): void
    {
        $this->expectException(NotFound::class);

        $this->service()->resetLead((object) ['id' => 'missing']);
    }

    private function service(bool $canEdit = true, string $userName = 'Coach'): LeadEventService
    {
        $handler = new class (function (string $leadId, array $context): void {
            $this->handled[] = [$leadId, $context];
        }) implements OutcomeHandler {
            public function __construct(private readonly Closure $onHandle)
            {
            }

            public function handle(string $leadId, array $context): OutcomeResult
            {
                ($this->onHandle)($leadId, $context);

                return (new OutcomeResult())->addEventId('ev1');
            }

            public function getEventTypes(): array
            {
                return [];
            }
        };

        $registry = $this->createStub(HandlerRegistry::class);
        foreach (['getCallHandler', 'getKickstartHandler', 'getKickstartFollowUpHandler', 'getRoyaltyHandler', 'getRoyaltyFollowUpHandler', 'getMessageHandler', 'getIntroMeetingHandler'] as $method) {
            $registry->method($method)->willReturnCallback(function (string $outcome) use ($method, $handler): OutcomeHandler {
                $this->lookups[] = "$method:$outcome";

                return $handler;
            });
        }
        foreach (['getKickstartBookingHandler', 'getRoyaltyBookingHandler'] as $method) {
            $registry->method($method)->willReturnCallback(function () use ($method, $handler): OutcomeHandler {
                $this->lookups[] = $method;

                return $handler;
            });
        }

        $callCountService = $this->createStub(LeadCallCountService::class);
        $callCountService->method('increment')->willReturnCallback(function (string $leadId): int {
            $this->callCount[] = "increment:$leadId";

            return 1;
        });
        $callCountService->method('reset')->willReturnCallback(function (string $leadId): void {
            $this->callCount[] = "reset:$leadId";
        });

        $eventLogService = $this->createStub(LeadEventLogService::class);
        $eventLogService->method('logEvent')->willReturnCallback(
            function (string $leadId, LeadEventType $type, ?string $date = null, ?string $description = null): array {
                $this->loggedEvents[] = [$leadId, $type, $date, $description];

                return ['success' => true, 'eventId' => 'ev-logged'];
            },
        );

        $acl = $this->createStub(Acl::class);
        $acl->method('checkEntityEdit')->willReturn($canEdit);

        $user = $this->createStub(User::class);
        $user->method('getName')->willReturn($userName);
        $user->method('getUserName')->willReturn('jan');

        $language = $this->createStub(Language::class);
        $language->method('translateOption')->willReturnCallback(
            static fn (string $value, string $field) => "[$field:$value]",
        );

        return new LeadEventService(
            $eventLogService,
            $callCountService,
            $registry,
            $this->inMemoryEntityManager(),
            $acl,
            $user,
            $language,
        );
    }
}
