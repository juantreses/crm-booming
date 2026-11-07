<?php

namespace Espo\Modules\Booking\Jobs;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Job\JobDataLess;
use Espo\Modules\Booking\Services\BookingEmailService;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\ORM\EntityManager;

class SendReminders implements JobDataLess
{
    private const int HOURS_AHEAD = 25;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BookingEmailService $emailService,
    ) {
    }

    public function run(): void
    {
        [$tomorrowStart, $tomorrowEnd] = $this->getWindow(new DateTimeImmutable('now'));

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

                $templateId = $this->emailService->getTemplateIdForMeeting($meeting, 'reminder');
                if (!$templateId) {
                    throw new \Exception('Geen reminder email gelinkt aan beschikbaarheid of kalender');
                }

                $this->emailService->sendMeetingEmail($meeting, $templateId);
                $meeting->set('cReminderSent', true);
                $meeting->set('cMailError');
            } catch (\Exception $e) {
                $meeting->set('cMailError', $e->getMessage());
            }

            $this->entityManager->saveEntity($meeting);
        }
    }

    /**
     * Meetings (in UTC) that get their reminder in a run at $now: from tomorrow on, up to 25 hours ahead.
     * The job runs every hour; looking 25 instead of 24 hours ahead makes the last run of the day
     * also reach the meetings in the last hour of tomorrow, which would otherwise never get a reminder.
     * Meetings of today get no reminder.
     *
     * @return array{string, string}
     */
    public function getWindow(DateTimeImmutable $now): array
    {
        $now = $now->setTimezone(new DateTimeZone('UTC'));

        return [
            $now->modify('tomorrow')->format('Y-m-d H:i:s'),
            $now->modify('+' . self::HOURS_AHEAD . ' hours')->format('Y-m-d H:i:s'),
        ];
    }
}
