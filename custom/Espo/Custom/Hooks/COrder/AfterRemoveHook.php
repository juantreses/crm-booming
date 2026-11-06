<?php

namespace Espo\Custom\Hooks\COrder;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Modules\ClientManager\Services\SavingsCardService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

class AfterRemoveHook implements AfterRemove
{
    public function __construct(
        private readonly SavingsCardService $savingsCardService,
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($entity->get('contactId')) {
            $this->savingsCardService->recalculateBalance($entity->get('contactId'));
        }
    }
}
