<?php

namespace Modules\Payroll\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Shares a pool among people by weight (Step 6.6). Pure.
 *
 * Each share is the pool × weight ÷ total weight, cut down to the cent; the cents left over go one each to the shares that lost
 * the most, the lower id first on a tie. So the shares always add up to the pool exactly, and the same input gives the same
 * answer. Weights of zero or less take no share.
 */
final class PoolDistributor
{
    /**
     * @param  array<int, string>  $weights  decimal weights by id
     * @return array<int, string> decimal amounts (two places) by id, for the ids that take a share
     */
    public function distribute(string $total, array $weights): array
    {
        $weights = array_filter($weights, fn (string $weight): bool => BigDecimal::of($weight)->isPositive());
        $cents = BigDecimal::of($total)->toScale(2, RoundingMode::HalfUp)->getUnscaledValue();

        if ($weights === [] || ! $cents->isPositive()) {
            return array_map(fn (string $weight): string => '0.00', $weights);
        }

        $sum = array_reduce($weights, fn (BigDecimal $carry, string $weight): BigDecimal => $carry->plus($weight), BigDecimal::zero());
        $floors = [];
        $fractions = [];
        $given = BigDecimal::zero();

        foreach ($weights as $id => $weight) {
            $exact = BigDecimal::of($cents)->multipliedBy($weight)->dividedBy($sum, 12, RoundingMode::Down);
            $floor = $exact->toScale(0, RoundingMode::Down);
            $floors[$id] = $floor;
            $fractions[$id] = $exact->minus($floor);
            $given = $given->plus($floor);
        }

        $left = $cents->minus($given)->toInt();
        $order = array_keys($fractions);
        usort($order, fn (int $a, int $b): int => $fractions[$b]->compareTo($fractions[$a]) ?: $a <=> $b);

        foreach (array_slice($order, 0, max(0, $left)) as $id) {
            $floors[$id] = $floors[$id]->plus(1);
        }

        return array_map(fn (BigDecimal $amount): string => (string) BigDecimal::ofUnscaledValue($amount->toBigInteger(), 2), $floors);
    }
}
