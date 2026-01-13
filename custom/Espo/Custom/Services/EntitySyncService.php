<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class EntitySyncService {

    private const BASE_FIELDS_TO_WATCH = [
        'firstName',
        'lastName',
        'emailAddress',
        'phoneNumber',
        'cTeamId',
        'cSlimFitCenterId'
    ];
    
    public function __construct(
        private readonly EntityManager $entityManager,
    ) {}

    public function syncFromLead(Entity $sourceEntity): void
    {
        if($this->hasFieldsChanged($sourceEntity) && $this->getRelatedContact($sourceEntity)){
            $this->syncBaseData($sourceEntity, $this->getRelatedContact($sourceEntity));
        }
    }
    public function syncFromContact(Entity $sourceEntity): void
    {
        if($this->hasFieldsChanged($sourceEntity) && $this->getRelatedLead($sourceEntity)){
            $this->syncBaseData($sourceEntity, $this->getRelatedLead($sourceEntity));
        }
    }

    private function getRelatedLead(Entity $contact): ?Entity
    {
        return $this->entityManager->getRelation($contact, 'originalLead')->findOne();
    }

    private function getRelatedContact(Entity $lead): ?Entity
    {
        return $this->entityManager->getRelation($lead, 'createdContact')->findOne();
    }

    private function syncBaseData(Entity $sourceEntity, Entity $destinationEntity): void
    {
        foreach(self::BASE_FIELDS_TO_WATCH as $field){
            if($this->hasFieldChanged($sourceEntity, $field)){
                $destinationEntity->set($field, $sourceEntity->get($field));
            }
        }

        $this->entityManager->saveEntity(
            $destinationEntity
        );
    }

    private function hasFieldsChanged(Entity $entity): bool
    {
        $changed = false;
        foreach (self::BASE_FIELDS_TO_WATCH as $field) {
            $changed = $changed || $this->hasFieldChanged($entity, $field);
            if($changed){
                return $changed;
            }
        }
        return $changed;
    }

    private function hasFieldChanged(Entity $entity, string $field): bool
    {
        if ($entity->isAttributeChanged($field)) {
            return true;
        }
        return false;
    }
}