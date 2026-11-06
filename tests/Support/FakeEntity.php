<?php

declare(strict_types=1);

namespace Tests\Support;

use Espo\ORM\BaseEntity;

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
     * Entity defs declaring the common attributes plus the keys of $values,
     * for building a specific entity class (e.g. Lead) in a test.
     *
     * @param array<string, mixed> $values
     * @return array{attributes: array<string, array<string, string>>}
     */
    public static function defs(array $values = []): array
    {
        $names = array_unique([...self::ATTRIBUTES, ...array_keys($values)]);
        $attributes = array_fill_keys($names, []);
        $attributes['id'] = ['type' => 'id'];

        return ['attributes' => $attributes];
    }
}
