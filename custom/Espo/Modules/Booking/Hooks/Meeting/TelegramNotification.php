<?php

namespace Espo\Modules\Booking\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\Telegram\Services\TelegramService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\ORM\EntityManager;

class TelegramNotification implements AfterSave
{

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly TelegramService $telegramService,
    ) {}

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $meeting, SaveOptions $options): void
    {
        $isNew = $meeting->isNew();
        $status = $meeting->get('status');

        $calendarId = $meeting->get('cCalendarId') ?? $meeting->get('calendarId');
        $calendar = $calendarId ? $this->entityManager->getEntityById('CCalendar', $calendarId) : null;

        $parentId = $meeting->get('parentId');
        $parentType = $meeting->get('parentType');
        $parent = ($parentId && $parentType) ? $this->entityManager->getEntityById($parentType, $parentId) : null;

        switch ($status) {
            case 'Tentative':
            case 'Planned':
                if ($isNew && $calendar && strtolower($calendar->get('type')) === 'kickstart') {
                    $this->sendKickstartAlert($meeting, $parent);
                }
                break;

            case 'Cancelled':
                if (!$isNew) {
                    $this->sendCancellationAlert($meeting, $parent, $calendar);
                }
                break;
        }
    }

    private function sendKickstartAlert(Entity $meeting, ?Entity $parent): void
    {
        if (!$parent) return;

        $formattedDate = $this->formatDate($meeting->get('dateStart'));

        $msg = "🚀 <b>Nieuwe Kickstart Gepland!</b>\n\n";
        
        $msg .= "👤 {$parent->get('name')}\n";
        
        if ($parent->get('emailAddress')) {
            $msg .= "✉️ {$parent->get('emailAddress')}\n";
        }
        
        $msg .= "📅 {$formattedDate}\n";
        
        $msg .= "🎓 {$parent->get('assignedUserName')}\n\n";
        
        
        $this->telegramService->sendMessage($msg);
    }

    private function sendCancellationAlert(Entity $meeting, ?Entity $parent, ?Entity $calendar): void
    {
        $formattedDate = $this->formatDate($meeting->get('dateStart'));
        
        $coachId = $parent?->get('assignedUserId');
        $coach = $coachId ? $this->entityManager->getEntityById('User', $coachId) : null;
        
        $tag = "";
        if ($coach && $coach->get('cTelegramUsername')) {
            $tag = " " . $coach->get('cTelegramUsername'); 
        }

        $type = $calendar?->get('type') ? ucfirst($calendar?->get('type')): 'Afspraak';
        $msg = "🚨 <b>{$type} Geannuleerd!</b>{$tag}\n\n";
        
        if ($parent) {
            $msg .= "👤 {$parent->get('name')}\n";
        }
        
        $msg .= "📅 {$formattedDate}\n";

        $this->telegramService->sendMessage($msg);
    }

    private function formatDate(?string $dateString): string
    {
        if (!$dateString) return 'Onbekend';

        $tz = new \DateTimeZone('Europe/Brussels');
        $date = new \DateTime($dateString);
        $date->setTimezone($tz);
        
        return $date->format('d/m/Y H:i');
    }

}