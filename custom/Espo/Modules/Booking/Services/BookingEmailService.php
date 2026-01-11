<?php

namespace Espo\Modules\Booking\Services;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Tools\EmailTemplate\Processor as MailTemplateProcessor;
use Espo\Core\Mail\EmailSender;
use Espo\Tools\EmailTemplate\Params as EmailTemplateParams;
use Espo\Tools\EmailTemplate\Data as EmailTemplateData;
use Espo\Modules\Crm\Entities\Meeting;

readonly class BookingEmailService
{
    public function __construct(
        private EntityManager $entityManager,
        private MailTemplateProcessor $mailTemplateProcessor,
        private EmailSender $emailSender,
    )
    {}

    public function sendMeetingEmail(Entity $meeting, string $templateId): void
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

        $emailData = $this->mailTemplateProcessor->process(
            $template,
            EmailTemplateParams::create(),
            EmailTemplateData::create()->withEntityHash([
                Meeting::ENTITY_TYPE => $meeting,
                'Person' => $person,
            ])
        );

        $email = $this->entityManager->getNewEntity('Email');
        $email->set([
            'subject' => $emailData->getSubject(),
            'body' => $emailData->getBody(),
            'isHtml' => $emailData->isHtml(),
            'parentType' => $person->getEntityType(),
            'parentId' => $person->get('id'),
            'to' => $person->get('emailAddress'),
        ]);

        $this->emailSender
            ->withAddedHeader('Auto-Submitted', 'auto-generated')
            ->withAttachments($emailData->getAttachmentList())
            ->send($email);

        $this->entityManager->saveEntity($email);

    }
}