<?php

namespace Espo\Modules\ClientManager\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Spaarkaart: clients earn 1 point per 10 volume points ordered (rounded down).
 * A card is valid for 1 year from its first order; the first order after that
 * starts a new card at 0. From HOM_POINTS on, a HOM can be booked; outcome A
 * redeems those points. PCX and distributor (DT) clients have no order history:
 * they earn no points and their balance is 0.
 */
readonly class SavingsCardService
{
    public const HOM_POINTS = 40;

    private const VOLUME_POINTS_PER_SAVINGS_POINT = 10;
    private const CARD_VALIDITY = '+1 year';

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
            && $this->isCardValid($contact)
            && (int) $contact->get('cSavingsPointsBalance') >= self::HOM_POINTS;
    }

    public function isCardValid(Entity $contact, ?DateTimeImmutable $today = null): bool
    {
        $end = $contact->get('cSavingsCardEnd');
        $today ??= new DateTimeImmutable('today');

        return $end && $today->format('Y-m-d') <= $end;
    }

    /**
     * Pass the contact entity when the caller saves it again afterwards, so it keeps the new balance.
     */
    public function recalculateBalance(Entity|string $contact, ?DateTimeImmutable $today = null): void
    {
        if (is_string($contact)) {
            $contact = $this->entityManager->getEntityById('Contact', $contact);
        }

        if (!$contact) {
            return;
        }

        $contactId = $contact->getId();

        $today ??= new DateTimeImmutable('today');

        $orders = $this->entityManager
            ->getRDBRepository('COrder')
            ->where(['contactId' => $contactId])
            ->order('dateOrdered', 'ASC')
            ->find();

        // Walk the orders in date order; an order after the card's validity starts a new card
        $cardStart = null;
        $earned = 0;

        foreach ($orders as $order) {
            $date = $order->get('dateOrdered');
            if (!$date) {
                continue;
            }

            if ($cardStart === null || $date > $this->cardEnd($cardStart)) {
                $cardStart = $date;
                $earned = 0;
            }

            $earned += (int) $order->get('savingsPoints');
        }

        if ($cardStart !== $contact->get('cSavingsCardStart')) {
            // New card: redemptions of the previous card no longer count
            $contact->set('cSavingsPointsRedeemed', 0);
        }

        $cardEnd = $cardStart ? $this->cardEnd($cardStart) : null;
        $isActive = $cardEnd && $today->format('Y-m-d') <= $cardEnd && $this->hasOrderHistory($contact);

        $contact->set([
            'cSavingsCardStart' => $cardStart,
            'cSavingsCardEnd' => $cardEnd,
            'cSavingsPointsBalance' => $isActive
                ? max(0, $earned - (int) $contact->get('cSavingsPointsRedeemed'))
                : 0,
        ]);
        $this->entityManager->saveEntity($contact);
    }

    /**
     * @throws BadRequest
     */
    public function redeemHomPoints(Entity $contact): void
    {
        if (!$this->isCardValid($contact) || (int) $contact->get('cSavingsPointsBalance') < self::HOM_POINTS) {
            throw new BadRequest('Onvoldoende geldige spaarkaartpunten (minimum ' . self::HOM_POINTS . ').');
        }

        $contact->set('cSavingsPointsRedeemed', (int) $contact->get('cSavingsPointsRedeemed') + self::HOM_POINTS);
        $this->entityManager->saveEntity($contact);

        $this->recalculateBalance($contact);
    }

    /**
     * Last day the card is valid (inclusive)
     */
    private function cardEnd(string $cardStart): string
    {
        return (new DateTimeImmutable($cardStart))
            ->modify(self::CARD_VALIDITY)
            ->modify('-1 day')
            ->format('Y-m-d');
    }
}
