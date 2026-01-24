<?php

namespace Espo\Modules\Booking\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\ORM\EntityManager;

class ConfirmationEmail implements AfterSave
{

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BookingEmailService $emailService
    ) {}

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $isNew = $entity->isNew();
        $status = $entity->get('status');
        $oldStatus = $entity->getFetched('status');

        $shouldSend = ($isNew && $status === 'Planned') || 
                      (!$isNew && $status === 'Planned' && $oldStatus !== 'Planned');

        if ($shouldSend) {
            $error = '';
            $calendarId = $entity->get('cCalendarId');
            if (!$calendarId) {
                $error = 'Geen kalender gelinkt aan meeting';
            }
            
            $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
            if (!$calendar) {
                $error = "Kalender met id $calendarId niet teruggevonden";
            }

            $templateId = $calendar->get('confirmationTemplateId');
            if (!$templateId) {
                $error = 'Geen bevestiging email gelinkt aan kalender';
            }

            if (!empty($error)) {
                $entity->set('cMailError', "Bevestiging fout: " . $error);
                return;
            }

            try {
                $this->emailService->sendMeetingEmail($entity, $templateId);
            } catch (\Exception $e) {
                $entity->set('cMailError', "Bevestiging fout: " . $e->getMessage());
                $this->entityManager->saveEntity($entity, ['silent' => true]);
            }
        }
    }
}