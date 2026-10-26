<?php

namespace Espo\Custom\Hooks\COrder;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\ClientManager\Services\SavingsCardService;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class BeforeSaveHook implements BeforeSave
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly SavingsCardService $savingsCardService,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $dateOrdered = $entity->get('dateOrdered');

        if ($dateOrdered) {
            $programEnd = (new \DateTime($dateOrdered))
                ->modify('+21 days')
                ->format('Y-m-d');

            $entity->set('programEnd', $programEnd);
        }

        $this->setSavingsPoints($entity);
    }

    private function setSavingsPoints(Entity $entity): void
    {
        // Points are fixed when the order is registered; a later change of client type keeps them
        if (
            !$entity->isNew()
            && !$entity->isAttributeChanged('volumePoints')
            && !$entity->isAttributeChanged('contactId')
        ) {
            return;
        }

        $contactId = $entity->get('contactId');
        $contact = $contactId ? $this->entityManager->getEntityById('Contact', $contactId) : null;

        $points = $contact && $this->savingsCardService->hasOrderHistory($contact)
            ? $this->savingsCardService->calculatePoints($entity->get('volumePoints'))
            : 0;

        $entity->set('savingsPoints', $points);
    }
}
