<?php

namespace Espo\Modules\LeadManager\ValueObjects;

readonly class OutcomeResult
{
    public function __construct(
        public array $eventIds = [],
        public bool $success = true,
    ) {}

    public function addEventId(string $eventId): self
    {
        return new self(
            eventIds: [...$this->eventIds, $eventId],
            success: $this->success,
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'eventIds' => $this->eventIds,
        ];
    }
}