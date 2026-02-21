<?php

namespace Espo\Modules\Booking\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\Modules\Crm\Business\Event\Ics;
use Espo\ORM\EntityManager;

class CancellationEmail implements AfterSave
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
        $status = $entity->get('status');
        $oldStatus = $entity->getFetched('status');

        if ($status === 'Cancelled' && (in_array($oldStatus, ['Planned', 'Tentative']))) {
            
            $calendarId = $entity->get('cCalendarId');
            if (!$calendarId) {
                $entity->set('cMailError', "Annulering fout: Geen kalender gelinkt aan meeting");
                return;
            }
            
            $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
            if (!$calendar) {
                $entity->set('cMailError', "Annulering fout: Kalender met id $calendarId niet teruggevonden");
                return;
            }

            $templateId = $calendar->get('cancellationTemplateId');
            if (!$templateId) {
                $entity->set('cMailError', "Annulering fout: Geen annulerings email gelinkt aan kalender");
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