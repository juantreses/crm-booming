<?php

declare(strict_types=1);

namespace Tests\Support;

use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

/**
 * Builds Entity stubs whose get()/getId() return fixed attribute values.
 *
 * @mixin TestCase
 */
trait EntityStubs
{
    /**
     * @param array<string, mixed> $attributes
     */
    private function entityStub(array $attributes): Entity
    {
        $entity = $this->createStub(Entity::class);
        $entity->method('get')->willReturnCallback(static fn (string $name) => $attributes[$name] ?? null);
        $entity->method('getId')->willReturn($attributes['id'] ?? '');

        return $entity;
    }
}
