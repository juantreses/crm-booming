<?php

namespace Espo\Modules\Booking\Services;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;

readonly class MeetingCancellationService
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
    ) {}

    /**
     * Sets the meeting to 'Cancelled' (the CancellationEmail hook sends the mail)
     * and removes it, which also removes it from Google Calendar.
     *
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function cancelAndRemove(string $meetingId): array
    {
        $meeting = $this->entityManager->getEntityById('Meeting', $meetingId);
        if (!$meeting) {
            throw new NotFound("Afspraak niet gevonden.");
        }

        if (!$this->acl->checkEntityEdit($meeting) || !$this->acl->checkEntityDelete($meeting)) {
            throw new Forbidden("Geen rechten om deze afspraak te annuleren.");
        }

        if (!in_array($meeting->get('status'), ['Planned', 'Tentative'])) {
            throw new BadRequest("Enkel geplande afspraken kunnen geannuleerd worden.");
        }

        $meeting->set('status', 'Cancelled');
        $this->entityManager->saveEntity($meeting);

        // The hook only sets the error on the entity in memory, so read it before removing.
        $mailError = $meeting->get('cMailError');

        $this->entityManager->removeEntity($meeting);

        return [
            'success' => true,
            'mailError' => $mailError ?: null,
        ];
    }
}
