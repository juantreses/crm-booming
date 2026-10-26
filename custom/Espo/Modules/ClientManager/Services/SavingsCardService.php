<?php

namespace Espo\Modules\ClientManager\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Spaarkaart: clients earn 1 point per 10 volume points ordered (rounded down).
 * From HOM_POINTS on, a HOM can be booked; outcome A redeems those points.
 * PCX and distributor (DT) clients have no order history, so they earn no points.
 */
readonly class SavingsCardService
{
    public const HOM_POINTS = 40;

    private const VOLUME_POINTS_PER_SAVINGS_POINT = 10;

    private const NO_ORDER_HISTORY_TYPES = [
        'pcx_15',
        'pcx_25',
        'pcx_35',
        'distributor',
        'distributeur',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function hasOrderHistory(Entity $contact): bool
    {
        return !in_array($contact->get('cTypeKlant'), self::NO_ORDER_HISTORY_TYPES, true);
    }

    public function calculatePoints(?float $volumePoints): int
    {
        return (int) floor(($volumePoints ?? 0) / self::VOLUME_POINTS_PER_SAVINGS_POINT);
    }

    public function canBookHom(Entity $contact): bool
    {
        return $this->hasOrderHistory($contact)
            && (int) $contact->get('cSavingsPointsBalance') >= self::HOM_POINTS;
    }

    public function recalculateBalance(string $contactId): void
    {
        $contact = $this->entityManager->getEntityById('Contact', $contactId);
        if (!$contact) {
            return;
        }

        $orders = $this->entityManager
            ->getRDBRepository('COrder')
            ->where(['contactId' => $contactId])
            ->find();

        $earned = 0;
        foreach ($orders as $order) {
            $earned += (int) $order->get('savingsPoints');
        }

        $contact->set('cSavingsPointsBalance', $earned - (int) $contact->get('cSavingsPointsRedeemed'));
        $this->entityManager->saveEntity($contact);
    }

    /**
     * @throws BadRequest
     */
    public function redeemHomPoints(Entity $contact): void
    {
        if ((int) $contact->get('cSavingsPointsBalance') < self::HOM_POINTS) {
            throw new BadRequest('Onvoldoende spaarkaartpunten (minimum ' . self::HOM_POINTS . ').');
        }

        $contact->set('cSavingsPointsRedeemed', (int) $contact->get('cSavingsPointsRedeemed') + self::HOM_POINTS);
        $this->entityManager->saveEntity($contact);

        $this->recalculateBalance($contact->getId());
    }
}
