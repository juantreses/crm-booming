<?php

namespace Espo\Custom\Hooks\COrder;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\ClientManager\Services\SavingsCardService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class AfterSaveHook implements AfterSave
{
    public function __construct(
        private readonly SavingsCardService $savingsCardService,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $contactIds = array_unique(array_filter([
            $entity->get('contactId'),
            $entity->getFetched('contactId'),
        ]));

        foreach ($contactIds as $contactId) {
            $this->savingsCardService->recalculateBalance($contactId);
        }
    }
}
