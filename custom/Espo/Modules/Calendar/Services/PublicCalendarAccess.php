<?php

namespace Espo\Modules\Calendar\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Entities\User;
use Espo\Modules\Calendar\Repositories\CalendarRepository;
use Espo\Modules\Utils\SlugService;
use Espo\ORM\Entity;

readonly class PublicCalendarAccess
{
    private const UNAVAILABLE_MESSAGE = 'Deze boekingslink is niet meer beschikbaar.';

    public function __construct(
        private SlugService $slugService,
        private CalendarRepository $calendarRepository,
        private User $user,
    ) {}

    /**
     * @throws NotFound
     */
    public function resolveAccessible(string $identifier, bool $allowAuthenticatedCrmUser = false): Entity
    {
        $calendarId = $this->slugService->resolve('CCalendar', $identifier);

        if (!$calendarId) {
            throw new NotFound(self::UNAVAILABLE_MESSAGE);
        }

        $calendar = $this->calendarRepository->findCalendarById($calendarId);

        if (!$calendar || !$calendar->get('isActive')) {
            throw new NotFound(self::UNAVAILABLE_MESSAGE);
        }

        if (
            (bool) $calendar->get('isPubliek') !== true &&
            !$allowAuthenticatedCrmUser &&
            !$this->isAuthenticatedCrmUser()
        ) {
            throw new NotFound(self::UNAVAILABLE_MESSAGE);
        }

        return $calendar;
    }

    private function isAuthenticatedCrmUser(): bool
    {
        if (!$this->user->isActive() || $this->user->isSystem() || $this->user->isPortal() || $this->user->isApi()) {
            return false;
        }

        return $this->user->isRegular() || $this->user->isAdmin();
    }
}
