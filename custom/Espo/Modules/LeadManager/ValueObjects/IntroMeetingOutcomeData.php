<?php

namespace Espo\Modules\LeadManager\ValueObjects;

use Espo\Custom\Enums\IntroMeetingOutcome;

readonly class IntroMeetingOutcomeData
{
    public function __construct(
        public string $leadId,
        public IntroMeetingOutcome $outcome,
        public ?string $introDateTime = null,
        public ?string $callAgainDateTime = null,
        public ?string $coachNote = null,
        public ?string $cancellationAction = null,
        public ?string $calendarId = null,
        public ?string $selectedDate = null,
        public ?string $selectedTime = null,
        public ?array $nextBooking = null,
    ) {}

    public static function fromStdClass(\StdClass $data): self
    {
        $nextBooking = null;

        if (isset($data->nextBooking)) {
            $raw = $data->nextBooking;

            if ($raw instanceof \StdClass) {
                $nextBooking = [
                    'calendarId' => $raw->calendarId ?? null,
                    'selectedDate' => $raw->selectedDate ?? null,
                    'selectedTime' => $raw->selectedTime ?? null,
                ];
            } elseif (is_array($raw)) {
                $nextBooking = [
                    'calendarId' => $raw['calendarId'] ?? null,
                    'selectedDate' => $raw['selectedDate'] ?? null,
                    'selectedTime' => $raw['selectedTime'] ?? null,
                ];
            }

            if ($nextBooking && (
                empty($nextBooking['calendarId'])
                || empty($nextBooking['selectedDate'])
                || empty($nextBooking['selectedTime'])
            )) {
                $nextBooking = null;
            }
        }

        return new self(
            leadId: (string) $data->id,
            outcome: IntroMeetingOutcome::from($data->outcome),
            introDateTime: $data->introDateTime ?? null,
            callAgainDateTime: $data->callAgainDateTime ?? null,
            coachNote: $data->coachNote ?? null,
            cancellationAction: $data->cancellationAction ?? null,
            calendarId: $data->calendarId ?? null,
            selectedDate: $data->selectedDate ?? null,
            selectedTime: $data->selectedTime ?? null,
            nextBooking: $nextBooking,
        );
    }
}
