<?php

namespace Espo\Modules\LeadManager\Services;

use Espo\Modules\Utils\DateTimeFactory;
use Espo\ORM\EntityManager;

readonly class LeadNotesService
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function addCoachNote(
        string $leadId,
        string $coachNote,
        string $source,
        ?string $eventDate = null
    ): void {
        $lead = $this->entityManager->getEntityById('Lead', $leadId);
        if (!$lead) {
            return;
        }

        $dt = DateTimeFactory::parseToBrussels($eventDate);
        $existingNotes = (string) ($lead->get('cNotes') ?? '');

        $formattedHeader = DateTimeFactory::formatBrusselsWithBrackers($dt);
        $newLine = "$formattedHeader ($source): $coachNote";
        $updatedNotes = $existingNotes ? ($newLine . "\n\n" . $existingNotes) : $newLine;

        $lead->set('cNotes', $updatedNotes);
        $this->entityManager->saveEntity($lead);
    }
}