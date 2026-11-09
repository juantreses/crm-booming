<?php

namespace Espo\Custom\Hooks\Contact;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\GroupAssignmentService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class BeforeSaveHook implements BeforeSave
{
    private const array FIELDS_TO_WATCH = [
        'cSlimFitCenter',
        'cTeam',
    ];
    public function __construct(
        private readonly Log $log,
        private readonly GroupAssignmentService $groupAssignmentService,
    ) {
    }

    public function beforeSave(Entity $contact, SaveOptions $options): void
    {
        try {
            $coachGroupName = trim(sprintf(
                '%s %s',
                (string) $contact->get('firstName'),
                (string) $contact->get('lastName'),
            ));

            $extraGroupNames = $coachGroupName !== '' ? [$coachGroupName] : [];

            $this->groupAssignmentService->syncGroupsFromFields(
                $contact,
                self::FIELDS_TO_WATCH,
                null,
                $extraGroupNames,
            );
            if (
                $contact->isNew()
                || $contact->isAttributeChanged('cTeamId')
            ) {
                $this->groupAssignmentService->syncAssignedUserFromTeamFields($contact);
            }
        } catch (\Exception $e) {
            $this->log->error('Contact Before Save Hook error: ' . $e->getMessage());
        }
    }
}
