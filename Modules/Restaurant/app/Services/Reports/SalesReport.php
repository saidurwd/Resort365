<?php

namespace Modules\Restaurant\Services\Reports;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Sales figures grouped by one dimension (ARCHITECTURE §5.10.14), without the database: rows of money in
 * cents are added up per label, with each label's share of the net sales; and spend per cover.
 *
 * A row is array{label: string, qty: float, net: int, discount: int, tax: int, gross: int}: net is what the
 * line sold for after discounts and before tax.
 */
class SalesReport
{
    /**
     * @param  list<array{label: string, qty: float, net: int, discount: int, tax: int, gross: int}>  $rows
     * @return list<array{label: string, qty: float, net: int, discount: int, tax: int, gross: int, share: string}> biggest net first
     */
    public function group(array $rows, bool $byLabel = false): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $group = $groups[$row['label']] ?? ['label' => $row['label'], 'qty' => 0.0, 'net' => 0, 'discount' => 0, 'tax' => 0, 'gross' => 0];
            $groups[$row['label']] = ['label' => $row['label'], 'qty' => $group['qty'] + $row['qty'], 'net' => $group['net'] + $row['net'], 'discount' => $group['discount'] + $row['discount'],
                'tax' => $group['tax'] + $row['tax'], 'gross' => $group['gross'] + $row['gross']];
        }

        $total = array_sum(array_column($groups, 'net'));
        $groups = array_map(fn (array $group): array => [...$group, 'share' => $total > 0 ? (string) BigDecimal::of($group['net'])->multipliedBy(100)->dividedBy($total, 1, RoundingMode::HalfUp) : '0.0'], array_values($groups));
        usort($groups, fn (array $a, array $b): int => $byLabel ? strnatcasecmp($a['label'], $b['label']) : ($b['net'] <=> $a['net'] ?: strnatcasecmp($a['label'], $b['label'])));

        return $groups;
    }

    /**
     * Net sales per cover in cents (half-up); 0 without covers.
     */
    public function perCover(int $net, int $covers): int
    {
        return $covers > 0 ? BigDecimal::of($net)->dividedBy($covers, 0, RoundingMode::HalfUp)->toInt() : 0;
    }
}
