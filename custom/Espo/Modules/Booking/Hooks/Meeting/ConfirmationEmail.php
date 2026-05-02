<?php

namespace Espo\Modules\Booking\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Modules\Booking\Services\BookingEmailService;

class ConfirmationEmail implements AfterSave
{

    public function __construct(
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

            $templateId = $this->emailService->getTemplateIdForMeeting($entity, 'confirmation');
            if (!$templateId) {
                $entity->set('cMailError', "Bevestiging fout: Geen bevestiging email gelinkt aan beschikbaarheid of kalender");
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
