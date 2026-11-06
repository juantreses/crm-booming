<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Links\ValueObjects;

use Espo\Modules\Links\ValueObjects\CalendarLink;
use Espo\Modules\Links\ValueObjects\LinkCollection;
use Espo\Modules\Links\ValueObjects\WidgetLink;
use PHPUnit\Framework\TestCase;

final class LinkValueObjectsTest extends TestCase
{
    public function testWidgetLinkToArray(): void
    {
        $link = new WidgetLink('survey', 'Enquête', 'https://crm.test/?entryPoint=widget&type=survey', 'fas fa-clipboard-list');

        self::assertSame([
            'type' => 'survey',
            'label' => 'Enquête',
            'url' => 'https://crm.test/?entryPoint=widget&type=survey',
            'icon' => 'fas fa-clipboard-list',
        ], $link->toArray());
    }

    public function testCalendarLinkToArrayOmitsNullAndFalse(): void
    {
        $link = new CalendarLink('Kickstart', 'https://crm.test/k');

        self::assertSame(['label' => 'Kickstart', 'url' => 'https://crm.test/k'], $link->toArray());
    }

    public function testCalendarLinkToArrayKeepsSetOptionalFields(): void
    {
        $link = new CalendarLink('Kickstart', 'https://crm.test/k', 'Alle locaties', isLocation: true, isVariant: true);

        self::assertSame([
            'label' => 'Kickstart',
            'url' => 'https://crm.test/k',
            'subtext' => 'Alle locaties',
            'isLocation' => true,
            'isVariant' => true,
        ], $link->toArray());
    }

    public function testCalendarLinkToArrayKeepsEmptyStringSubtext(): void
    {
        // array_filter only drops null and false, so an empty subtext is still sent to the client.
        $link = new CalendarLink('Kickstart', 'https://crm.test/k', '');

        self::assertSame(['label' => 'Kickstart', 'url' => 'https://crm.test/k', 'subtext' => ''], $link->toArray());
    }

    public function testLinkCollectionToArray(): void
    {
        $collection = new LinkCollection(
            [new WidgetLink('direct', 'Direct Boeken', 'https://crm.test/d', 'fas fa-bolt')],
            [new CalendarLink('Kickstart', 'https://crm.test/k')],
        );

        self::assertSame([
            'widgets' => [
                ['type' => 'direct', 'label' => 'Direct Boeken', 'url' => 'https://crm.test/d', 'icon' => 'fas fa-bolt'],
            ],
            'calendars' => [
                ['label' => 'Kickstart', 'url' => 'https://crm.test/k'],
            ],
        ], $collection->toArray());
    }

    public function testLinkCollectionHasLinks(): void
    {
        self::assertFalse((new LinkCollection([], []))->hasLinks());
        self::assertTrue((new LinkCollection([new WidgetLink('a', 'A', 'u', 'i')], []))->hasLinks());
        self::assertTrue((new LinkCollection([], [new CalendarLink('A', 'u')]))->hasLinks());
    }
}
