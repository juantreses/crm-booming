<?php

namespace Espo\Modules\Booking\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Core\Utils\Log;
use Espo\Tools\EmailTemplate\Processor as MailTemplateProcessor;
use Espo\ORM\EntityManager;
use Espo\Core\Mail\EmailSender;
use Espo\Tools\EmailTemplate\Params as EmailTemplateParams;
use Espo\Tools\EmailTemplate\Data as EmailTemplateData;
use Espo\Modules\Crm\Entities\Meeting;

class ConfirmationEmail implements AfterSave
{

    public function __construct(
        private readonly Log $log,
        private readonly EntityManager $entityManager,
        private readonly MailTemplateProcessor $mailTemplateProcessor,
        private readonly EmailSender $emailSender
    ) {}

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->log->info('Confirmation Email Hook triggered for Meeting ID: ' . $entity->getId());

        if (!$entity->get('cCalendarId')) {
            $this->log->info('No calendar found for Meeting ID: ' . $entity->getId());
            return;
        }
        $calendar = $this->entityManager->getEntityById('CCalendar', $entity->get('cCalendarId'));
        if (!$calendar) {
            $this->log->info('No calendar found for Meeting ID: ' . $entity->getId());
            return;
        }

        if (!$calendar->get('confirmationTemplateId')) {
            $this->log->info('No confirmation template found for Meeting ID: ' . $entity->getId());
            return;
        }
        $confirmationTemplate = $this->entityManager->getEntityById('EmailTemplate', $calendar->get('confirmationTemplateId'));
        if (!$confirmationTemplate) {
            $this->log->info('No email template found for ID: ' . $calendar->get('confirmationTemplateId'));
            return;
        }
        $person = $this->entityManager->getEntityById($entity->get('parentType'), $entity->get('parentId'));
        if (!$person) {
            $this->log->info('No person found for Meeting ID: ' . $entity->getId());
            return;
        }

        $isNew = $entity->isNew();
        $status = $entity->get('status');
        $oldStatus = $entity->getFetched('status');

        $shouldSend = ($isNew && $status === 'Planned') || 
                      (!$isNew && $status === 'Planned' && $oldStatus !== 'Planned');

        if (!$shouldSend) {
            return;
        }

        $this->processAndSend($entity, $calendar, $confirmationTemplate, $person);
    }

    private function processAndSend(Entity $meeting, Entity $calendar, Entity $confirmationTemplate, Entity $person): void
    {

        $startDate = (new \DateTime($meeting->get('dateStart')))->format('d/m/Y');
        $startTime = (new \DateTime($meeting->get('dateStart')))->format('H:i');

        $emailData = $this->mailTemplateProcessor->process(
            $confirmationTemplate,
            EmailTemplateParams::create(),
            EmailTemplateData::create()
                ->withEntityHash([
                    Meeting::ENTITY_TYPE => $meeting,
                    'Person' => $person,
                    //'startDate' => $startDate,
                    //'startTime' => $startTime,
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