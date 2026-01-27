<?php

namespace Espo\Modules\Booking\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\ORM\EntityManager;
use Espo\Modules\Crm\Entities\Meeting;
use DateTime;
use DateTimeZone;
use Espo\Modules\Booking\Services\BookingEmailService;

class SendReminders implements JobDataLess
{
    public function __construct(
        private EntityManager $entityManager,
        private BookingEmailService $emailService,
    ) {}

    public function run(): void
    {
        $tzUTC = new DateTimeZone('UTC');

        $tomorrowStart = (new DateTime('tomorrow', $tzUTC))->format('Y-m-d H:i:s');
        $tomorrowEnd = (new DateTime('now + 24 hours', $tzUTC))->format('Y-m-d H:i:s');

        $meetings = $this->entityManager->getRDBRepositoryByClass(Meeting::class)
            ->where([
                'dateStart>=' => $tomorrowStart,
                'dateStart<=' => $tomorrowEnd,
                'status' => 'Planned',
                'cReminderSent' => false,
            ])
            ->find();

        foreach ($meetings as $meeting) {
            try {
                $calendarId = $meeting->get('cCalendarId');
                if (!$calendarId) {
                    throw new \Exception('Geen kalender gelinkt aan meeting');
                }
                $calendar = $this->entityManager->getEntityById('CCalendar', $calendarId);
                if (!$calendar) {
                    throw new \Exception("Kalender met id $calendarId niet teruggevonden");
                }

                $templateId = $calendar->get('reminderTemplateId');
                if (!$templateId) {
                    throw new \Exception('Geen reminder email gelinkt aan kalender');
                }

                $this->emailService->sendMeetingEmail($meeting, $templateId);
                $meeting->set('cReminderSent', true);
                $meeting->set('cMailError', null);
            } catch (\Exception $e) {
                $meeting->set('cMailError', $e->getMessage());
            }

            $this->entityManager->saveEntity($meeting);
        }
    }
}