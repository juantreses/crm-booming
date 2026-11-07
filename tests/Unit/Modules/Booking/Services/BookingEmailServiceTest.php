<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Services;

use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Sender;
use Espo\Entities\Email;
use Espo\Entities\EmailTemplate;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\Modules\Crm\Business\Event\Ics;
use Espo\ORM\Entity;
use Espo\Tools\EmailTemplate\Data;
use Espo\Tools\EmailTemplate\Processor;
use Espo\Tools\EmailTemplate\Result;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeEntity;
use Tests\Support\InMemoryEntityManager;

final class BookingEmailServiceTest extends TestCase
{
    use InMemoryEntityManager;

    private string $originalTimezone;

    /** @var list<string> sender calls in order */
    private array $sent = [];

    private ?Data $templateData = null;

    protected function setUp(): void
    {
        // strtotime() on the meeting dates uses PHP's default timezone; Espo runs with UTC.
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        $this->addRecord(FakeEntity::create('CAvailability', ['id' => 'av1', 'confirmationTemplateId' => 'tpl-av-confirm']));
        $this->addRecord(FakeEntity::create('CCalendar', [
            'id' => 'cal1',
            'confirmationTemplateId' => 'tpl-cal-confirm',
            'reminderTemplateId' => 'tpl-cal-reminder',
        ]));
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    /**
     * @return iterable<string, array{array<string, ?string>, string, ?string}>
     */
    public static function templates(): iterable
    {
        yield 'availability template wins' => [['cAvailabilityId' => 'av1', 'cCalendarId' => 'cal1'], 'confirmation', 'tpl-av-confirm'];
        yield 'calendar when availability has none' => [['cAvailabilityId' => 'av1', 'cCalendarId' => 'cal1'], 'reminder', 'tpl-cal-reminder'];
        yield 'calendar without availability' => [['cCalendarId' => 'cal1'], 'confirmation', 'tpl-cal-confirm'];
        yield 'unknown availability falls back to calendar' => [['cAvailabilityId' => 'gone', 'cCalendarId' => 'cal1'], 'confirmation', 'tpl-cal-confirm'];
        yield 'none configured' => [['cCalendarId' => 'cal1'], 'cancellation', null];
        yield 'no calendar' => [['cAvailabilityId' => 'av1'], 'reminder', null];
        yield 'unknown calendar' => [['cCalendarId' => 'gone'], 'confirmation', null];
    }

    /**
     * @param array<string, ?string> $meetingValues
     */
    #[DataProvider('templates')]
    public function testTemplateForMeeting(array $meetingValues, string $type, ?string $expected): void
    {
        $meeting = FakeEntity::create('Meeting', ['id' => 'm1', ...$meetingValues]);

        self::assertSame($expected, $this->service()->getTemplateIdForMeeting($meeting, $type));
    }

    public function testUnknownTemplateType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Onbekend email type: herinnering');

        $this->service()->getTemplateIdForMeeting(FakeEntity::create('Meeting', ['id' => 'm1']), 'herinnering');
    }

    public function testSendMeetingEmail(): void
    {
        $templateAttachment = FakeEntity::create('Attachment', ['id' => 'tpl-att']);
        $meeting = $this->meetingWithPerson();

        $this->service($templateAttachment)->sendMeetingEmail($meeting, 'tpl1');

        $ics = $this->created[0];
        self::assertSame('Attachment', $ics->getEntityType());
        self::assertSame('Spark - Jan Peeters.ics', $ics->get('name'));
        self::assertSame('text/calendar', $ics->get('type'));
        self::assertSame('Attachment', $ics->get('role'));
        self::assertStringContainsString('METHOD:REQUEST', $ics->get('contents'));
        self::assertStringContainsString('DTSTART:20260120T180000Z', $ics->get('contents'));
        self::assertStringContainsString('DTEND:20260120T190000Z', $ics->get('contents'));
        self::assertStringContainsString('UID:m1', $ics->get('contents'));
        self::assertStringContainsString('STATUS:CONFIRMED', $ics->get('contents'));
        self::assertStringContainsString('DESCRIPTION:Notitie', $ics->get('contents'));

        self::assertNotNull($this->templateData);
        self::assertSame($meeting, $this->templateData->getEntityHash()['Meeting']);
        self::assertSame('lead1', $this->templateData->getEntityHash()['Person']->getId());

        $email = $this->created[1];
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Bevestiging', $email->get('subject'));
        self::assertSame('<p>Tot dan</p>', $email->get('body'));
        self::assertTrue($email->get('isHtml'));
        self::assertSame('Lead', $email->get('parentType'));
        self::assertSame('lead1', $email->get('parentId'));
        self::assertSame('jan@test.be', $email->get('to'));
        self::assertSame(['tpl-att', 'new1'], $email->get('attachmentsIds'));

        self::assertSame([
            'header Auto-Submitted: auto-generated',
            'attachments tpl-att, new1',
            'send new2',
        ], $this->sent);
        // The attachment is saved before sending, the email after.
        self::assertSame(['Attachment:new1', 'Email:new2'], $this->savedEntities());
    }

    public function testCancellationEmailHasCancelledIcs(): void
    {
        $this->service()->sendMeetingEmail($this->meetingWithPerson(), 'tpl1', Ics::METHOD_CANCEL);

        self::assertStringContainsString('METHOD:CANCEL', $this->created[0]->get('contents'));
        self::assertStringContainsString('STATUS:CANCELLED', $this->created[0]->get('contents'));
    }

    /**
     * @return iterable<string, array{array<string, ?string>, string}>
     */
    public static function unsendable(): iterable
    {
        yield 'unknown template' => [['templateId' => 'gone'], 'Email met id gone niet teruggevonden'];
        yield 'no parent' => [['parentId' => null], 'Meeting niet gekoppeld aan lead/contact'];
        yield 'unknown parent' => [['parentId' => 'gone'], 'Lead/Contact met id gone niet teruggevonden'];
    }

    /**
     * @param array<string, ?string> $overrides
     */
    #[DataProvider('unsendable')]
    public function testCannotSend(array $overrides, string $message): void
    {
        $meeting = $this->meetingWithPerson();
        if (array_key_exists('parentId', $overrides)) {
            $meeting->set('parentId', $overrides['parentId']);
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);

        try {
            $this->service()->sendMeetingEmail($meeting, $overrides['templateId'] ?? 'tpl1');
        } finally {
            self::assertSame([], $this->sent);
            self::assertSame([], $this->savedEntities());
        }
    }

    private function meetingWithPerson(): Entity
    {
        $this->addRecord(FakeEntity::typed(EmailTemplate::class, 'EmailTemplate', ['id' => 'tpl1']));
        $this->addRecord(FakeEntity::create('Lead', ['id' => 'lead1', 'emailAddress' => 'jan@test.be']));

        return FakeEntity::create('Meeting', [
            'id' => 'm1',
            'name' => 'Spark - Jan Peeters',
            'dateStart' => '2026-01-20 18:00:00',
            'dateEnd' => '2026-01-20 19:00:00',
            'description' => 'Notitie',
            'parentType' => 'Lead',
            'parentId' => 'lead1',
        ]);
    }

    /**
     * EmailSender::send() requires a real Email entity.
     */
    protected function newEntity(string $entityType, string $id): Entity
    {
        return $entityType === 'Email'
            ? FakeEntity::typed(Email::class, 'Email', ['id' => $id])
            : FakeEntity::create($entityType, ['id' => $id]);
    }

    private function service(?Entity $templateAttachment = null): BookingEmailService
    {
        $processor = $this->createStub(Processor::class);
        $processor->method('process')->willReturnCallback(function (Entity $template, $params, Data $data) use ($templateAttachment): Result {
            $this->templateData = $data;

            return new Result('Bevestiging', '<p>Tot dan</p>', true, $templateAttachment ? [$templateAttachment] : []);
        });

        $sender = $this->createStub(Sender::class);
        $sender->method('withAttachments')->willReturnCallback(function (iterable $attachments) use ($sender): Sender {
            $ids = [];
            foreach ($attachments as $attachment) {
                $ids[] = $attachment->getId();
            }
            $this->sent[] = 'attachments ' . implode(', ', $ids);

            return $sender;
        });
        $sender->method('send')->willReturnCallback(function (Email $email): void {
            $this->sent[] = 'send ' . $email->getId();
        });

        $emailSender = $this->createStub(EmailSender::class);
        $emailSender->method('withAddedHeader')->willReturnCallback(function (string $name, string $value) use ($sender): Sender {
            $this->sent[] = "header $name: $value";

            return $sender;
        });

        return new BookingEmailService($this->inMemoryEntityManager(), $processor, $emailSender);
    }
}
