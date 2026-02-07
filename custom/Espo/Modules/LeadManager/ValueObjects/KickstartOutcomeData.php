<?php

namespace Espo\Modules\LeadManager\ValueObjects;

use Espo\Custom\Enums\KickstartOutcome;

readonly class KickstartOutcomeData
{
    public function __construct(
        public string $leadId,
        public KickstartOutcome $outcome,
        public ?string $kickstartDateTime = null,
        public ?string $callAgainDateTime = null,
        public ?string $coachNote = null,
        public ?string $cancellationAction = null,
        public ?string $calendarId = null,
        public ?string $selectedDate = null,
        public ?string $selectedTime = null,
    ) {}

    public static function fromStdClass(\StdClass $data): self
    {
        return new self(
            leadId: (string) $data->id,
            outcome: KickstartOutcome::from($data->outcome),
            kickstartDateTime: $data->kickstartDateTime ?? null,
            callAgainDateTime: $data->callAgainDateTime ?? null,
            coachNote: $data->coachNote ?? null,
            cancellationAction: $data->cancellationAction ?? null,
            calendarId: $data->calendarId ?? null,
            selectedDate: $data->selectedDate ?? null,
            selectedTime: $data->selectedTime ?? null,
        );
    }
}