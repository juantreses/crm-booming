<?php

namespace Espo\Modules\ClientManager\Tools;

use Espo\Core\Console\Command;
use Espo\Core\Console\Command\Params;
use Espo\Core\Console\IO;
use Espo\Modules\ClientManager\Services\SavingsCardService;
use Espo\ORM\EntityManager;

/**
 * One-time backfill: calculate savings card points for existing orders
 * and the savings card balance of every client.
 * Run after Admin > Rebuild adds the new columns:
 *   php command.php backfill-savings-points
 */
class BackfillSavingsPoints implements Command
{
    public function __construct(
        private EntityManager $entityManager,
        private SavingsCardService $savingsCardService,
    ) {}

    public function run(Params $params, IO $io): void
    {
        $orders = $this->entityManager
            ->getRDBRepository('COrder')
            ->where(['deleted' => false])
            ->find();

        $contactIds = [];
        $updated = 0;

        foreach ($orders as $order) {
            $contactId = $order->get('contactId');
            $contact = $contactId ? $this->entityManager->getEntityById('Contact', $contactId) : null;

            $points = $contact && $this->savingsCardService->hasOrderHistory($contact)
                ? $this->savingsCardService->calculatePoints($order->get('volumePoints'))
                : 0;

            $order->set('savingsPoints', $points);
            $this->entityManager->saveEntity($order, ['silent' => true, 'skipHooks' => true]);
            $updated++;

            if ($contactId) {
                $contactIds[$contactId] = true;
            }
        }

        foreach (array_keys($contactIds) as $contactId) {
            $this->savingsCardService->recalculateBalance($contactId);
        }

        $io->writeLine("Backfilled savings points for {$updated} order(s) and " . count($contactIds) . " client(s).");
    }
}
