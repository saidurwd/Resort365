<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Cash-count arithmetic for closing a shift (ARCHITECTURE §5.9), without the database: the counted
 * cash from notes and coins by denomination, the cash the drawer should hold and the variance.
 * Amounts are decimal strings with two decimals; a positive variance is cash over, negative short.
 */
class CashCount
{
    /**
     * @param  array<int|string, int|string|null>  $counts  denomination => how many (blank = none)
     */
    public function counted(array $counts): string
    {
        $total = BigDecimal::zero();

        foreach ($counts as $denomination => $count) {
            $count = (int) ($count ?? 0);

            if ($count < 0 || ! is_numeric((string) $denomination) || BigDecimal::of((string) $denomination)->isNegativeOrZero()) {
                throw new InvalidArgumentException('Counts and denominations must be positive.');
            }

            $total = $total->plus(BigDecimal::of((string) $denomination)->multipliedBy($count));
        }

        return (string) $total->toScale(2);
    }

    public function expected(string $openingFloat, string $received, string $refunded): string
    {
        return (string) BigDecimal::of($openingFloat)->plus($received)->minus($refunded)->toScale(2);
    }

    public function variance(string $counted, string $expected): string
    {
        return (string) BigDecimal::of($counted)->minus($expected)->toScale(2);
    }

    /**
     * The denominations of a setting such as "1000,500,200": positive numbers, largest first.
     *
     * @return list<string>
     */
    public function denominations(string $setting): array
    {
        $values = array_values(array_unique(array_filter(array_map(trim(...), explode(',', $setting)), fn (string $value): bool => is_numeric($value) && (float) $value > 0)));
        usort($values, fn (string $a, string $b): int => BigDecimal::of($b)->compareTo($a));

        return $values;
    }
}
