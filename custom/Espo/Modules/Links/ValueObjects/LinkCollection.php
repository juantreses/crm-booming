<?php

namespace Espo\Modules\Links\ValueObjects;

readonly class LinkCollection
{
    /**
     * @param WidgetLink[] $widgets
     * @param CalendarLink[] $calendars
     */
    public function __construct(
        public array $widgets,
        public array $calendars,
    ) {}

    public function toArray(): array
    {
        return [
            'widgets' => array_map(fn($link) => $link->toArray(), $this->widgets),
            'calendars' => array_map(fn($link) => $link->toArray(), $this->calendars),
        ];
    }

    public function hasLinks(): bool
    {
        return count($this->widgets) > 0 || count($this->calendars) > 0;
    }
}