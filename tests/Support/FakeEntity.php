<?php

declare(strict_types=1);

namespace Tests\Support;

use Espo\ORM\BaseEntity;
use Espo\ORM\Entity;
use ReflectionClass;
use ReflectionNamedType;

/**
 * In-memory Espo entity for tests: a real BaseEntity (so get/set/getId behave like in Espo),
 * without database or metadata. Every attribute our code reads or writes must be declared,
 * otherwise BaseEntity silently ignores set() calls for it.
 */
final class FakeEntity
{
    /**
     * Attributes our custom code uses on Lead, Contact, CTeam, CCalendar, Meeting, User and CLeadEvent.
     */
    private const ATTRIBUTES = [
        'id', 'name', 'status', 'type', 'slug', 'cSlug', 'createdAt',
        'firstName', 'lastName', 'emailAddress', 'phoneNumber', 'source', 'cSourceUrl',
        'assignedUserId', 'cTeamId', 'cSlimFitCenterId', 'slimFitCenterId', 'maxCallAttempts',
        'cStage', 'cReasonLost', 'cMeetingType', 'cSparksUsed', 'cCallCount',
        'cNotes', 'cFollowUpAction', 'cSurveyData', 'cReferredBy',
        'eventType', 'eventDate', 'description',
        // Meeting and calendar.
        'dateStart', 'dateEnd', 'parentId', 'parentType', 'cCalendarId', 'cLocationId', 'cAvailabilityId',
        'cMailError', 'cReminderSent', 'duration', 'needsApproval', 'assignedUserName', 'cTelegramUsername',
        'confirmationTemplateId', 'reminderTemplateId', 'cancellationTemplateId',
        // Email and attachment.
        'subject', 'body', 'isHtml', 'to', 'attachmentsIds', 'role', 'contents',
        // Notes.
        'note', 'contactId',
        // Teams (Espo groups) and orders.
        'teamsIds', 'sponserId', 'programEnd', 'dateOrdered', 'amount', 'savingsPoints', 'cDateAssigned',
    ];

    /**
     * @param array<string, mixed> $values
     */
    public static function create(string $entityType, array $values = []): BaseEntity
    {
        $entity = new BaseEntity($entityType, self::defs($values));
        $entity->set($values);

        return $entity;
    }

    /**
     * Like create(), but as a specific Espo entity class (e.g. EmailTemplate, Email) for code that
     * type-hints it. Required collaborators of its constructor (EntityManager, ...) are created without
     * running their constructor, so only use this for classes that don't call them on get/set.
     * Person entities (Lead, Contact) do: build those with stubbed collaborators instead (see LeadServiceTest).
     *
     * @template T of Entity
     * @param class-string<T> $class
     * @param array<string, mixed> $values
     * @return T
     */
    public static function typed(string $class, string $entityType, array $values = []): Entity
    {
        $arguments = [$entityType, self::defs($values)];

        foreach (array_slice((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [], 2) as $parameter) {
            $type = $parameter->getType();

            if ($parameter->isOptional() || !$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                break;
            }

            $arguments[] = (new ReflectionClass($type->getName()))->newInstanceWithoutConstructor();
        }

        $entity = new $class(...$arguments);
        $entity->set($values);

        return $entity;
    }

    /**
     * An entity as loaded from the database (not new), optionally with unsaved changes on top,
     * so isNew(), getFetched() and isAttributeChanged() behave like in a save hook.
     *
     * @param array<string, mixed> $values
     * @param array<string, mixed> $changes
     */
    public static function fetched(string $entityType, array $values, array $changes = []): BaseEntity
    {
        $entity = self::create($entityType, $values);
        $entity->setAsFetched();
        $entity->set($changes);

        return $entity;
    }

    /**
     * Entity defs declaring the common attributes plus the keys of $values,
     * for building a specific entity class (e.g. Lead) in a test.
     *
     * @param array<string, mixed> $values
     * @return array{attributes: array<string, array{type: string}>}
     */
    public static function defs(array $values = []): array
    {
        $names = array_unique([...self::ATTRIBUTES, ...array_keys($values)]);
        // An unknown type keeps values as they are (no casting) and compares them with ===.
        $attributes = array_fill_keys($names, ['type' => 'mixed']);
        $attributes['id'] = ['type' => 'id'];

        return ['attributes' => $attributes];
    }
}
