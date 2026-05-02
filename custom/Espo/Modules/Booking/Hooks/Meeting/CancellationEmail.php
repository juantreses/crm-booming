<?php

namespace Espo\Modules\Booking\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\Modules\Crm\Business\Event\Ics;

class CancellationEmail implements AfterSave
{

    public function __construct(
        private readonly BookingEmailService $emailService
    ) {}

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $status = $entity->get('status');
        $oldStatus = $entity->getFetched('status');

        if ($status === 'Cancelled' && (in_array($oldStatus, ['Planned', 'Tentative']))) {
            $calendarId = $entity->get('cCalendarId');
            if (!$calendarId) {
                $entity->set('cMailError', "Annulering fout: Geen kalender gelinkt aan meeting");
                return;
            }

            $templateId = $this->emailService->getTemplateIdForMeeting($entity, 'cancellation');
            if (!$templateId) {
                $entity->set('cMailError', "Annulering fout: Geen annulerings email gelinkt aan beschikbaarheid of kalender");
                return;                
            }
            
            if ($templateId) {
                try {
                    $this->emailService->sendMeetingEmail($entity, $templateId, Ics::METHOD_CANCEL);
                } catch (\Exception $e) {
                    $entity->set('cMailError', "Annulering mail fout: " . $e->getMessage());
                }
            }
        }
    }
}
