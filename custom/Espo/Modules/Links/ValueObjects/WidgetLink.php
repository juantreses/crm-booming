<?php

namespace Espo\Modules\Links\ValueObjects;

readonly class WidgetLink
{
    public function __construct(
        public string $type,
        public string $label,
        public string $url,
        public string $icon,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}