<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Utils;

use Espo\Core\Utils\Metadata;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SlugServiceTest extends TestCase
{
    private const ESPO_ID = '68cbf30d9582059c2';

    public function testReturnsNullForEmptyIdentifier(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->expects(self::never())->method('getRDBRepository');

        $service = new SlugService($entityManager, $this->createStub(Metadata::class));

        self::assertNull($service->resolve('CCalendar'));
        self::assertNull($service->resolve('CCalendar', null));
        self::assertNull($service->resolve('CCalendar', ''));
    }

    public function testReturnsEspoIdUnchangedWithoutLookup(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->expects(self::never())->method('getRDBRepository');

        $service = new SlugService($entityManager, $this->createStub(Metadata::class));

        self::assertSame(self::ESPO_ID, $service->resolve('CCalendar', self::ESPO_ID));
    }

    public function testLooksUpBySlugField(): void
    {
        $service = new SlugService(
            $this->entityManagerFinding('CCalendar', 'slug', 'kickstart-gent', $this->entity('cal1')),
            $this->metadataWithFields('CCalendar', ['slug']),
        );

        self::assertSame('cal1', $service->resolve('CCalendar', 'kickstart-gent'));
    }

    public function testFallsBackToCustomSlugField(): void
    {
        $service = new SlugService(
            $this->entityManagerFinding('User', 'cSlug', 'jan', $this->entity('user1')),
            $this->metadataWithFields('User', ['cSlug']),
        );

        self::assertSame('user1', $service->resolve('User', 'jan'));
    }

    public function testPrefersSlugOverCustomSlug(): void
    {
        $service = new SlugService(
            $this->entityManagerFinding('CCalendar', 'slug', 'gent', $this->entity('cal1')),
            $this->metadataWithFields('CCalendar', ['slug', 'cSlug']),
        );

        self::assertSame('cal1', $service->resolve('CCalendar', 'gent'));
    }

    public function testReturnsNullWhenEntityTypeHasNoSlugField(): void
    {
        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->expects(self::never())->method('getRDBRepository');

        $service = new SlugService($entityManager, $this->metadataWithFields('Lead', []));

        self::assertNull($service->resolve('Lead', 'some-slug'));
    }

    public function testReturnsNullWhenNothingFound(): void
    {
        $service = new SlugService(
            $this->entityManagerFinding('CCalendar', 'slug', 'unknown', null),
            $this->metadataWithFields('CCalendar', ['slug']),
        );

        self::assertNull($service->resolve('CCalendar', 'unknown'));
    }

    public function testReturnsNullWhenLookupThrows(): void
    {
        $entityManager = $this->createStub(EntityManager::class);
        $entityManager->method('getRDBRepository')->willThrowException(new RuntimeException('DB down'));

        $service = new SlugService($entityManager, $this->metadataWithFields('CCalendar', ['slug']));

        self::assertNull($service->resolve('CCalendar', 'gent'));
    }

    /**
     * @param string[] $fields
     */
    private function metadataWithFields(string $entityType, array $fields): Metadata
    {
        $metadata = $this->createStub(Metadata::class);
        $metadata->method('get')->willReturnCallback(
            static fn (array $path) => $path[0] === 'entityDefs'
                && $path[1] === $entityType
                && in_array($path[3], $fields, true)
                    ? ['type' => 'varchar']
                    : null,
        );

        return $metadata;
    }

    private function entityManagerFinding(string $entityType, string $field, string $value, ?Entity $result): EntityManager
    {
        $builder = $this->createMock(RDBSelectBuilder::class);
        $builder->expects(self::once())->method('findOne')->willReturn($result);

        $repository = $this->createMock(RDBRepository::class);
        $repository->expects(self::once())->method('where')->with($field, $value)->willReturn($builder);

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->expects(self::once())->method('getRDBRepository')->with($entityType)->willReturn($repository);

        return $entityManager;
    }

    private function entity(string $id): Entity
    {
        $entity = $this->createStub(Entity::class);
        $entity->method('getId')->willReturn($id);

        return $entity;
    }
}
