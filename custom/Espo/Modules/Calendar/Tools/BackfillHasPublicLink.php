<?php

namespace Espo\Modules\Calendar\Tools;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\ORM\EntityManager;

/**
 * One-time backfill: copy isPubliek to hasPublicLink for existing calendars.
 * Run after Admin > Rebuild adds the hasPublicLink column:
 *   php command.php backfill-has-public-link
 */
class BackfillHasPublicLink implements Command
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function run(Params $params, IO $io): void
    {
        $collection = $this->entityManager
            ->getRDBRepository('CCalendar')
            ->where(['deleted' => false])
            ->find();

        $updated = 0;

        foreach ($collection as $calendar) {
            $calendar->set('hasPublicLink', (bool) $calendar->get('isPubliek'));
            $this->entityManager->saveEntity($calendar, ['silent' => true]);
            $updated++;
        }

        $io->writeLine("Backfilled hasPublicLink for {$updated} calendar(s).");
    }
}
