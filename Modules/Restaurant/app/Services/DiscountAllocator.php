<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Restaurant\Enums\DiscountType;

/**
 * Discount money (ARCHITECTURE §5.10.7), without the database: what a percentage or an amount takes off
 * a base (never more than the base), and how a bill discount is shared between the lines in proportion
 * to what they cost after their own discounts. Cents throughout.
 */
class DiscountAllocator
{
    /**
     * The discount in cents of a percentage (half-up) or an amount, capped at the base.
     */
    public function amount(int $baseCents, DiscountType $type, string $value): int
    {
        $cents = $type === DiscountType::Percent
            ? BigDecimal::of($baseCents)->multipliedBy($value)->dividedBy(100, 0, RoundingMode::HalfUp)->toInt()
            : BigDecimal::of($value)->multipliedBy(100)->toScale(0, RoundingMode::HalfUp)->toInt();

        return max(0, min($baseCents, $cents));
    }

    /**
     * The percentage a discount is of its base (for the role limits), to 2 decimals.
     */
    public function percentOf(int $discountCents, int $baseCents): string
    {
        return $baseCents <= 0 ? '0.00' : (string) BigDecimal::of($discountCents)->multipliedBy(100)->dividedBy($baseCents, 2, RoundingMode::HalfUp);
    }

    /**
     * @param  list<int>  $lineBases  each line's cents after its own discount
     * @return list<int> each line's share of the bill discount
     */
    public function share(int $discountCents, array $lineBases): array
    {
        if ($lineBases === [] || $discountCents === 0 || array_sum($lineBases) <= 0) {
            return array_fill(0, count($lineBases), 0);
        }

        return Allocation::largestRemainder($discountCents, $lineBases);
    }
}
