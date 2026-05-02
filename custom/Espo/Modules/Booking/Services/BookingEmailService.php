<?php

namespace Espo\Modules\Booking\Services;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Tools\EmailTemplate\Processor as MailTemplateProcessor;
use Espo\Core\Mail\EmailSender;
use Espo\Tools\EmailTemplate\Params as EmailTemplateParams;
use Espo\Tools\EmailTemplate\Data as EmailTemplateData;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Crm\Business\Event\Ics;

readonly class BookingEmailService
{
    private const TEMPLATE_FIELD_MAP = [
        'confirmation' => 'confirmationTemplateId',
        'reminder' => 'reminderTemplateId',
        'cancellation' => 'cancellationTemplateId',
    ];

    public function __construct(
        private EntityManager $entityManager,
        private MailTemplateProcessor $mailTemplateProcessor,
        private EmailSender $emailSender,
    )
    {}

    public function getTemplateIdForMeeting(Entity $meeting, string $templateType): ?string
    {
        $templateField = self::TEMPLATE_FIELD_MAP[$templateType] ?? null;
        if (!$templateField) {
            throw new \InvalidArgumentException("Onbekend email type: $templateType");
        }

        $availabilityId = $meeting->get('cAvailabilityId');
        if ($availabilityId) {
            $availability = $this->entityManager->getEntityById('CAvailability', $availabilityId);
            if ($availability && $availability->get($templateField)) {
                return $availability->get($templateField);
            }
        }

        $calendarId = $meeting->get('cCalendarId');
        if (!$calendarId) {
            return null;
        }

        $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);

        return $calendar?->get($templateField);
    }

    public function sendMeetingEmail(Entity $meeting, string $templateId, string $icsMethod = Ics::METHOD_REQUEST): void
    {
        $template = $this->entityManager->getEntityById('EmailTemplate', $templateId);
        if (!$template) {
            throw new \Exception("Email met id $templateId niet teruggevonden");
        }

        $parentId = $meeting->get('parentId');
        if (!$parentId) {
            throw new \Exception("Meeting niet gekoppeld aan lead/contact");
        }
        $person = $this->entityManager->getEntityById($meeting->get('parentType'), $parentId);
        if (!$person) {
            throw new \Exception("Lead/Contact met id $parentId niet teruggevonden");
        }

        $icsStatus = ($icsMethod === Ics::METHOD_CANCEL) ? Ics::STATUS_CANCELLED : Ics::STATUS_CONFIRMED;

        $ics = new Ics('//EspoCRM//Booming CRM//EN', [
            'method' => $icsMethod,
            'status' => $icsStatus,
            'startDate' => strtotime($meeting->get('dateStart')),
            'endDate' => strtotime($meeting->get('dateEnd')),
            'uid' => $meeting->get('id'),
            'summary' => $meeting->get('name'),
            'description' => $meeting->get('description')
        ]);

        $icsContent = $ics->get();

        $attachment = $this->entityManager->getNewEntity('Attachment');
        $attachment->set([
            'name' =>  $meeting->get('name') . '.ics',
            'type' => 'text/calendar',
            'role' => 'Attachment',
            'contents' => $icsContent
        ]);
        $this->entityManager->saveEntity($attachment);

        $emailData = $this->mailTemplateProcessor->process(
            $template,
            EmailTemplateParams::create(),
            EmailTemplateData::create()->withEntityHash([
                Meeting::ENTITY_TYPE => $meeting,
                'Person' => $person,
            ])
        );

        $attachments = $emailData->getAttachmentList();
        $attachments[] = $attachment;

        $email = $this->entityManager->getNewEntity('Email');
        $email->set([
            'subject' => $emailData->getSubject(),
            'body' => $emailData->getBody(),
            'isHtml' => $emailData->isHtml(),
            'parentType' => $person->getEntityType(),
            'parentId' => $person->get('id'),
            'to' => $person->get('emailAddress'),
            'attachmentsIds' => array_map(fn($a) => $a->get('id'), $attachments) 
        ]);

        $this->emailSender
            ->withAddedHeader('Auto-Submitted', 'auto-generated')
            ->withAttachments($attachments)
            ->send($email);

        $this->entityManager->saveEntity($email);
    }
}
