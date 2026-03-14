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
                      (!$isNew && $status === 'Planned' && $oldStatus === 'Tentative');

        if ($shouldSend) {
            $calendarId = $entity->get('cCalendarId');
            if (!$calendarId) {
                $entity->set('cMailError', "Bevestiging fout: Geen kalender gelinkt aan meeting");
                return;
            }
            
            $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
            if (!$calendar) {
                $entity->set('cMailError', "Bevestiging fout: Kalender met id $calendarId niet teruggevonden");
                return;
            }

            $templateId = $calendar->get('confirmationTemplateId');
            if (!$templateId) {
                $entity->set('cMailError', "Bevestiging fout: Geen bevestiging email gelinkt aan kalender");
                return;                
            }

            try {
                $this->emailService->sendMeetingEmail($entity, $templateId);
            } catch (\Exception $e) {
                $entity->set('cMailError', "Bevestiging fout: " . $e->getMessage());
            }
        }
    }
}