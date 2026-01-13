<?php

namespace Espo\Modules\Downlines\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

readonly class DownlinesApi
{

    public function __construct(
        private EntityManager $entityManager,
    )
    {}

    public function getDownlines(string $teamId, int $maxDepth = 6): array
    {
        $entity = $this->entityManager->getEntityById('CTeam', $teamId);
        if (!$entity) {
            throw new NotFound("CTeam $teamId not found");
        }

        return $this->collectDownlines($entity, 0, $maxDepth);
    }

    private function collectDownlines(Entity $team, int $depth, int $maxDepth): array
    {
        if ($depth >= $maxDepth) {
            return [];
    	}

        $downlines = $this->entityManager->getRDBRepository('CTeam')->getRelation($team, 'downlines')->find();

        $result = [];

        foreach ($downlines as $downline) {
            $result[] = [
                'id'   => $downline->getId(),
                'name' => $downline->get('name'),
                'depth' => $depth + 1,
                'children' => $this->collectDownlines($downline, $depth + 1, $maxDepth)
            ];
        }

        return $result;

    }
}
