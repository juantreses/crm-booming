<?php

namespace Espo\Modules\LeadManager\ValueObjects;

use Espo\Custom\Enums\KickstartOutcome;

readonly class KickstartFollowUpOutcomeData
{
    public function __construct(
        public string $leadId,
        public KickstartOutcome $outcome,
        public ?string $followUpDateTime = null,
        public ?string $coachNote = null,
    ) {}

    public static function fromStdClass(\StdClass $data): self
    {
        return new self(
            leadId: (string) $data->id,
            outcome: KickstartOutcome::from($data->outcome),
            followUpDateTime: $data->followUpDateTime ?? null,
            coachNote: $data->coachNote ?? null,
        );
    }
}