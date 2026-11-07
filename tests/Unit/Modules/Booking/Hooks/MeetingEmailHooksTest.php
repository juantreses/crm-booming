<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Hooks;

use Espo\Modules\Booking\Hooks\Meeting\CancellationEmail;
use Espo\Modules\Booking\Hooks\Meeting\ConfirmationEmail;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\Modules\Crm\Business\Event\Ics;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;

final class MeetingEmailHooksTest extends TestCase
{
    /** @var list<string> "type" per template lookup and "send template method" per mail */
    private array $calls = [];

    /**
     * @return iterable<string, array{?string, string, bool}>
     */
    public static function confirmationCases(): iterable
    {
        // status before (null = new meeting), status after => mail sent
        yield 'new planned' => [null, 'Planned', true];
        yield 'new tentative' => [null, 'Tentative', false];
        yield 'approved' => ['Tentative', 'Planned', true];
        yield 'still planned' => ['Planned', 'Planned', false];
        yield 'held' => ['Planned', 'Held', false];
        yield 'cancelled' => ['Planned', 'Cancelled', false];
    }

    #[DataProvider('confirmationCases')]
    public function testConfirmationMail(?string $before, string $after, bool $sent): void
    {
        $meeting = $this->meeting($before, $after);

        (new ConfirmationEmail($this->emailService()))->afterSave($meeting, SaveOptions::fromAssoc([]));

        self::assertSame($sent ? ['template confirmation', 'send tpl-confirmation REQUEST'] : [], $this->calls);
        self::assertNull($meeting->get('cMailError'));
    }

    /**
     * @return iterable<string, array{?string, string, bool}>
     */
    public static function cancellationCases(): iterable
    {
        yield 'planned cancelled' => ['Planned', 'Cancelled', true];
        yield 'tentative cancelled' => ['Tentative', 'Cancelled', true];
        yield 'held then cancelled' => ['Held', 'Cancelled', false];
        yield 'new cancelled' => [null, 'Cancelled', false];
        yield 'already cancelled' => ['Cancelled', 'Cancelled', false];
        yield 'planned' => [null, 'Planned', false];
    }

    #[DataProvider('cancellationCases')]
    public function testCancellationMail(?string $before, string $after, bool $sent): void
    {
        $meeting = $this->meeting($before, $after);

        (new CancellationEmail($this->emailService()))->afterSave($meeting, SaveOptions::fromAssoc([]));

        self::assertSame($sent ? ['template cancellation', 'send tpl-cancellation CANCEL'] : [], $this->calls);
    }

    /**
     * @return iterable<string, array{class-string, ?string, array<string, mixed>, string}>
     */
    public static function mailErrors(): iterable
    {
        $confirmation = ConfirmationEmail::class;
        $cancellation = CancellationEmail::class;

        yield 'confirmation without calendar' => [$confirmation, null, ['cCalendarId' => null], 'Bevestiging fout: Geen kalender gelinkt aan meeting'];
        yield 'confirmation without template' => [
            $confirmation, null, ['template' => null], 'Bevestiging fout: Geen bevestiging email gelinkt aan beschikbaarheid of kalender',
        ];
        yield 'confirmation send fails' => [$confirmation, null, ['fail' => 'SMTP down'], 'Bevestiging fout: SMTP down'];
        yield 'cancellation without calendar' => [$cancellation, 'Planned', ['cCalendarId' => null], 'Annulering fout: Geen kalender gelinkt aan meeting'];
        yield 'cancellation without template' => [
            $cancellation, 'Planned', ['template' => null], 'Annulering fout: Geen annulerings email gelinkt aan beschikbaarheid of kalender',
        ];
        yield 'cancellation send fails' => [$cancellation, 'Planned', ['fail' => 'SMTP down'], 'Annulering mail fout: SMTP down'];
    }

    /**
     * @param class-string<ConfirmationEmail|CancellationEmail> $hook
     * @param array<string, mixed> $setup
     */
    #[DataProvider('mailErrors')]
    public function testMailErrorIsKeptOnTheMeeting(string $hook, ?string $before, array $setup, string $error): void
    {
        $meeting = $this->meeting($before, $hook === ConfirmationEmail::class ? 'Planned' : 'Cancelled');
        if (array_key_exists('cCalendarId', $setup)) {
            $meeting->set('cCalendarId', $setup['cCalendarId']);
        }

        $service = $this->emailService(
            array_key_exists('template', $setup) ? $setup['template'] : 'tpl',
            $setup['fail'] ?? null,
        );

        (new $hook($service))->afterSave($meeting, SaveOptions::fromAssoc([]));

        self::assertSame($error, $meeting->get('cMailError'));
    }

    private function meeting(?string $before, string $after): Entity
    {
        $values = ['id' => 'm1', 'cCalendarId' => 'cal1', 'cMailError' => null];

        return $before === null
            ? FakeEntity::create('Meeting', [...$values, 'status' => $after])
            : FakeEntity::fetched('Meeting', [...$values, 'status' => $before], ['status' => $after]);
    }

    private function emailService(?string $templatePrefix = 'tpl', ?string $failure = null): BookingEmailService
    {
        $service = $this->createStub(BookingEmailService::class);
        $service->method('getTemplateIdForMeeting')->willReturnCallback(function (Entity $meeting, string $type) use ($templatePrefix): ?string {
            $this->calls[] = "template $type";

            return $templatePrefix ? "$templatePrefix-$type" : null;
        });
        $service->method('sendMeetingEmail')->willReturnCallback(
            function (Entity $meeting, string $templateId, string $method = Ics::METHOD_REQUEST) use ($failure): void {
                if ($failure) {
                    throw new Exception($failure);
                }

                $this->calls[] = "send $templateId $method";
            },
        );

        return $service;
    }
}
