<?php

namespace Espo\Modules\ClientManager\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Modules\ClientManager\Services\SavingsCardService;
use Espo\ORM\EntityManager;

/**
 * Daily: set the balance of expired savings cards to 0.
 */
class ExpireSavingsCards implements JobDataLess
{
    public function __construct(
        private EntityManager $entityManager,
        private SavingsCardService $savingsCardService,
    ) {}

    public function run(): void
    {
        $contacts = $this->entityManager
            ->getRDBRepository('Contact')
            ->where([
                'cSavingsCardEnd<' => date('Y-m-d'),
                'cSavingsPointsBalance>' => 0,
            ])
            ->find();

        foreach ($contacts as $contact) {
            $this->savingsCardService->recalculateBalance($contact->getId());
        }
    }
}
