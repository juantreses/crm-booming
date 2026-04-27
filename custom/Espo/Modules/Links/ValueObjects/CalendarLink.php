<?php

namespace Espo\Modules\Links\ValueObjects;

readonly class CalendarLink
{
    public function __construct(
        public string $label,
        public string $url,
        public ?string $subtext = null,
        public bool $isLocation = false,
        public bool $isVariant = false,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'label' => $this->label,
            'url' => $this->url,
            'subtext' => $this->subtext,
            'isLocation' => $this->isLocation,
            'isVariant' => $this->isVariant,
        ], fn($value) => $value !== null && $value !== false);
    }
}
