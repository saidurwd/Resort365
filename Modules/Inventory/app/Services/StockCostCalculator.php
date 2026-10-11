<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Weighted-average stock costing (Step 5.3). A balance is a quantity and an average cost per base unit (four
 * decimals). Stock coming in is averaged with what is on hand; stock going out leaves at the current average, which
 * does not change. A reversal that takes stock out at the cost it came in at re-averages what is left. When the
 * balance is zero or negative, stock coming in sets the average to its own cost. Pure; amounts are decimal strings.
 */
class StockCostCalculator
{
    /**
     * Apply a signed quantity to a balance.
     *
     * @param  string|null  $unitCost  the cost per unit of the stock coming in (default: the current average), or of stock going out when it is a reversal (default: the current average)
     * @return array{quantity: string, avg_cost: string, unit_cost: string} the new balance and the cost per unit of this movement
     */
    public function apply(string $quantity, string $avgCost, string $signedQuantity, ?string $unitCost = null): array
    {
        $on = BigDecimal::of($quantity);
        $avg = BigDecimal::of($avgCost);
        $move = BigDecimal::of($signedQuantity);
        $cost = $unitCost !== null ? BigDecimal::of($unitCost) : $avg;
        $after = $on->plus($move);

        if ($move->isPositive()) {
            $newAvg = $on->isPositive() ? $on->multipliedBy($avg)->plus($move->multipliedBy($cost))->dividedBy($after, 4, RoundingMode::HalfUp) : $cost->toScale(4, RoundingMode::HalfUp);
        } elseif ($unitCost !== null && $after->isPositive()) {
            $newAvg = BigDecimal::max($on->multipliedBy($avg)->minus($move->abs()->multipliedBy($cost))->dividedBy($after, 4, RoundingMode::HalfUp), BigDecimal::zero());
        } else {
            $newAvg = $avg->toScale(4, RoundingMode::HalfUp);
        }

        return ['quantity' => (string) $after->toScale(4), 'avg_cost' => (string) $newAvg->toScale(4), 'unit_cost' => (string) $cost->toScale(4, RoundingMode::HalfUp)];
    }

    /**
     * The value of a quantity at a unit cost, to two decimals.
     */
    public function value(string $quantity, string $unitCost): string
    {
        return (string) BigDecimal::of($quantity)->abs()->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp);
    }
}
