<?php

namespace Espo\Modules\Links\Services;

use Espo\Core\Utils\Config;
use Espo\Modules\Links\ValueObjects\WidgetLink;

readonly class WidgetLinkBuilder
{
    private const WIDGET_TYPES = [
        ['type' => 'survey', 'label' => 'Enquête', 'icon' => 'fas fa-clipboard-list'],
        ['type' => 'voucher', 'label' => 'Voucher', 'icon' => 'fas fa-ticket-alt'],
        ['type' => 'direct', 'label' => 'Direct Boeken', 'icon' => 'fas fa-bolt'],
        ['type' => 'referral', 'label' => 'Referral', 'icon' => 'fas fa-user-friends'],
    ];

    public function __construct(
        private Config $config,
    ) {}

    /**
     * Build widget links
     * 
     * @param string|null $coachIdentifier Coach slug or null for center-wide links
     * @return WidgetLink[]
     */
    public function build(?string $coachIdentifier): array
    {
        $baseUrl = rtrim($this->config->get('siteUrl'), '/');
        $baseWidgetUrl = "$baseUrl/?entryPoint=widget";
        $coachParam = $coachIdentifier ? "&coach=$coachIdentifier" : "";

        $links = [];
        foreach (self::WIDGET_TYPES as $widget) {
            $links[] = new WidgetLink(
                type: $widget['type'],
                label: $widget['label'],
                url: "{$baseWidgetUrl}&type={$widget['type']}{$coachParam}",
                icon: $widget['icon']
            );
        }

        return $links;
    }
}