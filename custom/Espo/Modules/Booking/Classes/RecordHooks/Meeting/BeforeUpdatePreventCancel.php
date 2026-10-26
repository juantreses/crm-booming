<?php

namespace Espo\Modules\Booking\Classes\RecordHooks\Meeting;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\Hook\UpdateHook;
use Espo\Core\Record\UpdateParams;
use Espo\ORM\Entity;

/**
 * Blocks setting a meeting to 'Cancelled' through a regular edit. Cancelling must go
 * through the 'Annuleer & Verwijder' action so the meeting is also removed from Google Calendar.
 *
 * @implements UpdateHook<Entity>
 */
class BeforeUpdatePreventCancel implements UpdateHook
{
    public function process(Entity $entity, UpdateParams $params): void
    {
        if (!$entity->isAttributeChanged('status') || $entity->get('status') !== 'Cancelled') {
            return;
        }

        throw new Forbidden("Gebruik de knop 'Annuleer & Verwijder' om een afspraak te annuleren.");
    }
}
