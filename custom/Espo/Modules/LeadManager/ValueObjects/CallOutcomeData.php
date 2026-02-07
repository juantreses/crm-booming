<?php

namespace Espo\Modules\LeadManager\ValueObjects;

use Espo\Custom\Enums\CallOutcome;

readonly class CallOutcomeData
{
    public function __construct(
        public string $leadId,
        public CallOutcome $outcome,
        public ?string $callDateTime = null,
        public ?string $callAgainDateTime = null,
        public ?string $coachNote = null,
        public ?string $calendarId = null,
        public ?string $selectedDate = null,
        public ?string $selectedTime = null,
    ) {}

    public static function fromStdClass(\StdClass $data): self
    {
        return new self(
            leadId: (string) $data->id,
            outcome: CallOutcome::from($data->outcome),
            callDateTime: $data->callDateTime ?? null,
            callAgainDateTime: $data->callAgainDateTime ?? null,
            coachNote: $data->coachNote ?? null,
            calendarId: $data->calendarId ?? null,
            selectedDate: $data->selectedDate ?? null,
            selectedTime: $data->selectedTime ?? null,
        );
    }
}