<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\LeadManager\Handlers;

use Espo\Core\Utils\Log;
use Espo\Custom\Enums\LeadEventType;
use Espo\Modules\LeadManager\Handlers\OutcomeHandler;
use Espo\Modules\LeadManager\Services\IntroMeetingService;
use Espo\Modules\LeadManager\Services\LeadEventLogService;
use Espo\Modules\LeadManager\Services\LeadFollowUpService;
use Espo\Modules\LeadManager\Services\LeadMeetingService;
use Espo\Modules\LeadManager\Services\LeadNotesService;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

/**
 * Wires a handler with recording collaborators. After handle() the test compares:
 *
 * - $events:    "event_type@date" per logged lead event (date '-' when none)
 * - $notes:     "Source: note" per coach note
 * - $followUps: "clear", "add:Note@datetime" or "text:Text" per follow-up change
 * - $meetings:  "create:calendar date time", "held:id", "notHeld:id" or "cancelled:id" ('none' without planned meeting)
 *
 * IntroMeetingService is the real one, so SPARK limits work as in production.
 */
abstract class HandlerTestCase extends TestCase
{
    use InMemoryEntityManager;

    /** @var list<string> */
    protected array $events = [];

    /** @var list<string> */
    protected array $notes = [];

    /** @var list<string> */
    protected array $followUps = [];

    /** @var list<string> */
    protected array $meetings = [];

    protected ?Entity $plannedMeeting = null;

    private mixed $originalLog = null;

    protected function setUp(): void
    {
        // Message\InvitedHandler logs through Espo's global logger.
        $this->originalLog = $GLOBALS['log'] ?? null;
        $GLOBALS['log'] = $this->createStub(Log::class);

        $this->plannedMeeting = FakeEntity::create('Meeting', ['id' => 'meeting1', 'status' => 'Planned']);
    }

    protected function tearDown(): void
    {
        $GLOBALS['log'] = $this->originalLog;
    }

    /**
     * @param array<string, mixed> $values
     */
    protected function lead(array $values = []): Entity
    {
        return $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', ...$values]));
    }

    /**
     * Adds a calendar; SlugService resolves its slug (and its id) to the id.
     */
    protected function calendar(string $id, string $type, ?string $slug = null): Entity
    {
        return $this->addRecord(FakeEntity::create('CCalendar', ['id' => $id, 'type' => $type, 'slug' => $slug]));
    }

    /**
     * @template T of OutcomeHandler
     * @param class-string<T> $class
     * @return T
     */
    protected function handler(string $class): OutcomeHandler
    {
        $entityManager = $this->inMemoryEntityManager();

        $collaborators = [
            LeadEventLogService::class => $this->eventLogService(),
            LeadNotesService::class => $this->notesService(),
            LeadFollowUpService::class => $this->followUpService(),
            LeadMeetingService::class => $this->meetingService(),
            EntityManager::class => $entityManager,
            IntroMeetingService::class => new IntroMeetingService($entityManager),
            SlugService::class => $this->slugService(),
        ];

        $arguments = [];

        foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || !isset($collaborators[$type->getName()])) {
                throw new LogicException("No test collaborator for \${$parameter->getName()} of $class");
            }

            $arguments[] = $collaborators[$type->getName()];
        }

        return new $class(...$arguments);
    }

    private function eventLogService(): LeadEventLogService
    {
        $service = $this->createStub(LeadEventLogService::class);
        $service->method('logEvent')->willReturnCallback(
            function (string $leadId, LeadEventType $type, ?string $date = null): array {
                $this->events[] = $type->value . '@' . ($date ?? '-');

                return ['success' => true, 'eventId' => 'ev' . count($this->events)];
            },
        );

        return $service;
    }

    private function notesService(): LeadNotesService
    {
        $service = $this->createStub(LeadNotesService::class);
        $service->method('addCoachNote')->willReturnCallback(
            function (string $leadId, string $note, string $source): void {
                $this->notes[] = "$source: $note";
            },
        );

        return $service;
    }

    private function followUpService(): LeadFollowUpService
    {
        $service = $this->createStub(LeadFollowUpService::class);
        $service->method('clearFollowUpAction')->willReturnCallback(function (): void {
            $this->followUps[] = 'clear';
        });
        $service->method('addFollowUpAction')->willReturnCallback(
            function (string $leadId, string $dateTime, string $note = 'Opnieuw bellen'): void {
                $this->followUps[] = "add:$note@$dateTime";
            },
        );
        $service->method('setFollowUpActionText')->willReturnCallback(function (string $leadId, string $text): void {
            $this->followUps[] = "text:$text";
        });

        return $service;
    }

    private function meetingService(): LeadMeetingService
    {
        $service = $this->createStub(LeadMeetingService::class);
        $service->method('createInternalMeeting')->willReturnCallback(
            function (string $calendarId, Entity $lead, string $date, string $time): void {
                $this->meetings[] = "create:$calendarId $date $time";
            },
        );
        $service->method('findPlannedMeeting')->willReturnCallback(fn () => $this->plannedMeeting);

        foreach (['markAsHeld' => 'held', 'markAsNotHeld' => 'notHeld', 'markAsCancelled' => 'cancelled'] as $method => $label) {
            $service->method($method)->willReturnCallback(function (?Entity $meeting) use ($label): void {
                $this->meetings[] = "$label:" . ($meeting?->getId() ?? 'none');
            });
        }

        return $service;
    }

    private function slugService(): SlugService
    {
        $service = $this->createStub(SlugService::class);
        $service->method('resolve')->willReturnCallback(function (string $type, ?string $identifier): ?string {
            foreach ($this->records[$type] ?? [] as $id => $entity) {
                if ($identifier === $id || ($identifier !== null && $identifier === $entity->get('slug'))) {
                    return $id;
                }
            }

            return null;
        });

        return $service;
    }
}
