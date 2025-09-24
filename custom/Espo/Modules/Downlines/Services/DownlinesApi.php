<?php

namespace Espo\Modules\Downlines\Services;

use Espo\Core\Controllers\RecordBase;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\Entity;

class DownlinesApi extends RecordBase 
{
    public function getDownlines(string $teamId, int $maxDepth = 6): array
    {
        $entity = $this->getEntityManager()->getEntity('CTeam', $teamId);
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

        $relation = $this->getEntityManager()->getRepository('CTeam')->getRelation($team, 'downlines');
	$downlines = $relation->find();

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
