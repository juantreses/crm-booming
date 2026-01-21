<?php

namespace Espo\Modules\Utils;

use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Throwable;

readonly class SlugService
{
    public function __construct(
        private EntityManager $entityManager,
        private Metadata $metadata,
    )
    {}

    public function resolve(string $entityType, ?string $identifier = null): ?string
    {
        if (!$identifier) {
            return null;
        }
        
        if (preg_match('/^[a-f0-9-]{17}$/', $identifier)) {
            return $identifier;
        }

        $fieldName = $this->getSlugFieldName($entityType);
        if (!$fieldName) {
            return null;
        }

        try {
            $entity = $this->entityManager
                ->getRDBRepository($entityType)
                ->where($fieldName, $identifier)
                ->findOne();

            return $entity?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    private function getSlugFieldName(string $entityType): ?string
    {
        if ($this->metadata->get(['entityDefs', $entityType, 'fields', 'slug'])) {
            return 'slug';
        }

        if ($this->metadata->get(['entityDefs', $entityType, 'fields', 'cSlug'])) {
            return 'cSlug';
        }

        return null;
    }
}