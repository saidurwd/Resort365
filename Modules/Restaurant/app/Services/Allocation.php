<?php

namespace Modules\Restaurant\Services;

use InvalidArgumentException;

/**
 * Shares a whole number of cents between parts in proportion to weights, so the parts always add up
 * to the total (largest remainder; ties go to the earlier part). Pure.
 */
final class Allocation
{
    /**
     * @param  list<int|float>  $weights  not negative, at least one above zero
     * @return list<int>
     */
    public static function largestRemainder(int $total, array $weights): array
    {
        $sum = array_sum($weights);

        if ($weights === [] || $sum <= 0 || min($weights) < 0) {
            throw new InvalidArgumentException('Weights must be positive.');
        }

        $sign = $total < 0 ? -1 : 1;
        $total = abs($total);
        $parts = [];
        $remainders = [];

        foreach ($weights as $index => $weight) {
            $exact = $total * $weight / $sum;
            $parts[$index] = (int) floor($exact);
            $remainders[$index] = $exact - $parts[$index];
        }

        $left = $total - array_sum($parts);
        arsort($remainders); // stable for equal remainders: earlier parts first

        foreach (array_keys($remainders) as $index) {
            if ($left <= 0) {
                break;
            }

            $parts[$index]++;
            $left--;
        }

        ksort($parts);

        return array_values(array_map(fn (int $part): int => $part * $sign, $parts));
    }
}
