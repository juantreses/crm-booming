<?php

namespace Espo\Custom\Hooks\COrder;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;

class BeforeSaveHook implements BeforeSave
{
    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $dateOrdered = $entity->get('dateOrdered');

        if ($dateOrdered) {
            $programEnd = (new \DateTime($dateOrdered))
                ->modify('+21 days')
                ->format('Y-m-d');

            $entity->set('programEnd', $programEnd);
        }
    }
}
