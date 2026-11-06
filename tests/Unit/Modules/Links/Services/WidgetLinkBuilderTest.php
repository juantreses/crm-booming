<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Links\Services;

use Espo\Core\Utils\Config;
use Espo\Modules\Links\Services\WidgetLinkBuilder;
use Espo\Modules\Links\ValueObjects\WidgetLink;
use PHPUnit\Framework\TestCase;

final class WidgetLinkBuilderTest extends TestCase
{
    public function testBuildsAllWidgetTypesInOrder(): void
    {
        $links = $this->builder('https://crm.test')->build(null);

        self::assertCount(4, $links);
        self::assertSame(
            ['survey', 'voucher', 'direct', 'referral'],
            array_map(static fn (WidgetLink $link) => $link->type, $links),
        );
        self::assertSame(
            ['Enquête', 'Voucher', 'Direct Boeken', 'Referral'],
            array_map(static fn (WidgetLink $link) => $link->label, $links),
        );
        self::assertSame(
            ['fas fa-clipboard-list', 'fas fa-ticket-alt', 'fas fa-bolt', 'fas fa-user-friends'],
            array_map(static fn (WidgetLink $link) => $link->icon, $links),
        );
    }

    public function testUrlWithoutCoach(): void
    {
        $links = $this->builder('https://crm.test')->build(null);

        self::assertSame('https://crm.test/?entryPoint=widget&type=survey', $links[0]->url);
    }

    public function testUrlWithCoach(): void
    {
        $links = $this->builder('https://crm.test')->build('jan');

        self::assertSame('https://crm.test/?entryPoint=widget&type=referral&coach=jan', $links[3]->url);
    }

    public function testEmptyCoachIsTreatedAsNoCoach(): void
    {
        $links = $this->builder('https://crm.test')->build('');

        self::assertSame('https://crm.test/?entryPoint=widget&type=survey', $links[0]->url);
    }

    public function testTrailingSlashOfSiteUrlIsRemoved(): void
    {
        $links = $this->builder('https://crm.test/')->build(null);

        self::assertSame('https://crm.test/?entryPoint=widget&type=survey', $links[0]->url);
    }

    private function builder(string $siteUrl): WidgetLinkBuilder
    {
        $config = $this->createStub(Config::class);
        $config->method('get')->willReturnMap([['siteUrl', null, $siteUrl]]);

        return new WidgetLinkBuilder($config);
    }
}
