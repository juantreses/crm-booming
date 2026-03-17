<?php

namespace Espo\Modules\LeadManager\ValueObjects;

use Espo\Custom\Enums\MessageSentOutcome;

readonly class MessageOutcomeData
{
    public function __construct(
        public string $leadId,
        public MessageSentOutcome $outcome,
        public ?string $callAgainDateTime = null,
        public ?string $coachNote = null,
        public ?string $meetingType = null,
        public ?string $calendarId = null,
        public ?string $selectedDate = null,
        public ?string $selectedTime = null,
    ) {}

    public static function fromStdClass(\StdClass $data): self
    {
        return new self(
            leadId: (string) $data->id,
            outcome: MessageSentOutcome::from($data->outcome),
            callAgainDateTime: $data->callAgainDateTime ?? null,
            coachNote: $data->coachNote ?? null,
            meetingType: $data->meetingType ?? null,
            calendarId: $data->calendarId ?? null,
            selectedDate: $data->selectedDate ?? null,
            selectedTime: $data->selectedTime ?? null,
        );
    }
}