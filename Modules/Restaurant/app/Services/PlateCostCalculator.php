<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The cost of a portion and what it means for the price (Step 5.7): quantities times unit costs added up and rounded once
 * (half up, two places), the cost as a percent of the price, and the price that would give a target percent.
 */
class PlateCostCalculator
{
    /**
     * @param  array<int, string>  $quantities  item id => quantity per portion
     * @param  array<int, string>  $unitCosts  item id => cost per base unit (an item with no cost counts as free)
     */
    public function cost(array $quantities, array $unitCosts): string
    {
        $total = BigDecimal::zero();

        foreach ($quantities as $itemId => $quantity) {
            $total = $total->plus(BigDecimal::of($quantity)->multipliedBy($unitCosts[$itemId] ?? '0'));
        }

        return (string) $total->toScale(2, RoundingMode::HalfUp);
    }

    /** The cost as a percent of the selling price (null for a free item). */
    public function percent(string $cost, string $price): ?string
    {
        return BigDecimal::of($price)->isPositive() ? (string) BigDecimal::of($cost)->multipliedBy('100')->dividedBy($price, 2, RoundingMode::HalfUp) : null;
    }

    /** The price at which the cost is the target percent (null when there is no target). */
    public function suggestedPrice(string $cost, string $targetPercent): ?string
    {
        return BigDecimal::of($targetPercent)->isPositive() ? (string) BigDecimal::of($cost)->multipliedBy('100')->dividedBy($targetPercent, 2, RoundingMode::Up) : null;
    }
}
