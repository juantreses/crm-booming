<?php

declare(strict_types=1);

namespace Tests\Support;

use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Query builder stubs for repository code: every chain is recorded as one line in $queries,
 * e.g. 'CCalendar where {"isActive":true} order createdAt ASC findOne', and find()/findOne()
 * return the entities set in $queryResults for that entity type (or "Type.link" for relations).
 *
 * @mixin TestCase
 */
trait RecordingQueries
{
    /** @var list<string> */
    protected array $queries = [];

    /** @var array<string, list<Entity>> entity type (or "Type.link") => results */
    protected array $queryResults = [];

    protected function recordingRepository(string $entityType): RDBRepository
    {
        $repository = $this->createStub(RDBRepository::class);

        foreach (['where', 'leftJoin', 'distinct', 'order'] as $method) {
            $repository->method($method)->willReturnCallback(
                fn (mixed ...$arguments) => $this->recordingBuilder($entityType, count($this->queries))
                    ->$method(...$arguments),
            );
        }

        $repository->method('getRelation')->willReturnCallback(function (Entity $entity, string $link) use ($entityType): RDBRelation {
            $key = "$entityType.$link";
            $relation = $this->createStub(RDBRelation::class);
            $relation->method('find')->willReturnCallback(function () use ($key, $entity): EntityCollection {
                $this->queries[] = "$key of {$entity->getId()} find";

                return new EntityCollection($this->queryResults[$key] ?? []);
            });

            return $relation;
        });

        return $repository;
    }

    private function recordingBuilder(string $entityType, int $index): RDBSelectBuilder
    {
        $this->queries[$index] = $entityType;

        $builder = $this->createStub(RDBSelectBuilder::class);
        $builder->method('where')->willReturnCallback(function (mixed $clause = [], mixed $value = null) use ($builder, $index): RDBSelectBuilder {
            $this->queries[$index] .= ' where ' . (is_array($clause) ? json_encode($clause) : "$clause=" . json_encode($value));

            return $builder;
        });
        $builder->method('leftJoin')->willReturnCallback(function (string $target, ?string $alias = null) use ($builder, $index): RDBSelectBuilder {
            $this->queries[$index] .= " leftJoin $target $alias";

            return $builder;
        });
        $builder->method('distinct')->willReturnCallback(function () use ($builder, $index): RDBSelectBuilder {
            $this->queries[$index] .= ' distinct';

            return $builder;
        });
        $builder->method('order')->willReturnCallback(function (string $orderBy, ?string $direction = null) use ($builder, $index): RDBSelectBuilder {
            $this->queries[$index] .= rtrim(" order $orderBy $direction");

            return $builder;
        });
        $builder->method('find')->willReturnCallback(function () use ($entityType, $index): EntityCollection {
            $this->queries[$index] .= ' find';

            return new EntityCollection($this->queryResults[$entityType] ?? []);
        });
        $builder->method('findOne')->willReturnCallback(function () use ($entityType, $index): ?Entity {
            $this->queries[$index] .= ' findOne';

            return $this->queryResults[$entityType][0] ?? null;
        });

        return $builder;
    }
}
