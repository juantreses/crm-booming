<?php

namespace Espo\Modules\Utils;

use Espo\ORM\EntityManager;
use Throwable;

readonly class SlugService
{
    public function __construct(
        private EntityManager $entityManager,
    )
    {}

    public function resolve(string $entityType, string $identifier): ?string
    {
        if (preg_match('/^[a-f0-9-]{17}$/', $identifier)) {
            return $identifier;
        }

        try {
            $entity = $this->entityManager
                ->getRDBRepository($entityType)
                ->where('slug', $identifier)
                ->findOne();

            return $entity?->getId();
        } catch (Throwable) {
            return null;
        }
    }
}