<?php

namespace Modules\Restaurant\Services;

use InvalidArgumentException;

/**
 * Splits an order's money into bills (ARCHITECTURE §5.10.7), without the database, so that the bills
 * always add up to the order to the cent, each bill is consistent (gross = amount − discount + taxes
 * when prices exclude tax; gross = amount − discount when they include it), and every line's shares
 * add up to the line.
 *
 * A line is array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}
 * (cents; amount is the line before discounts, taxes per tax code).
 *
 *  - byLines(): lines (or units of a line) are given to bills (split by item or seat); each line's
 *    money is shared between its bills in proportion to the units given.
 *  - byWeights(): the order's totals are shared between the bills in proportion to weights (equal
 *    split: 1 each; split by amount: the amounts, which the bills then come to exactly); each bill
 *    lists every line with its share.
 *
 * A bill is array{amount: int, discount: int, taxes: array<string, int>, gross: int,
 *   lines: list<array{id: int, quantity: string, amount: int, discount: int, tax: int, gross: int}>}.
 */
class BillSplitter
{
    /**
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @return list<array<string, mixed>>
     */
    public function single(array $lines, bool $inclusive): array
    {
        return $this->byLines($lines, $inclusive, array_fill_keys(array_column($lines, 'id'), [0 => 1]), 1);
    }

    /**
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @param  array<int, array<int, int>>  $assignments  line id => [bill index => units (or weight)]
     * @return list<array<string, mixed>>
     */
    public function byLines(array $lines, bool $inclusive, array $assignments, int $billCount): array
    {
        $bills = $this->emptyBills($billCount, $this->codes($lines));

        foreach ($lines as $line) {
            $given = array_filter($assignments[$line['id']] ?? [], fn (int $units): bool => $units > 0);

            if ($given === [] || max(array_keys($given)) >= $billCount || min(array_keys($given)) < 0) {
                throw new InvalidArgumentException("Line {$line['id']} is not on a bill.");
            }

            $indexes = array_keys($given);
            $weights = array_values($given);
            $amount = Allocation::largestRemainder($line['amount'], $weights);
            $discount = Allocation::largestRemainder($line['discount'], $weights);
            $taxes = array_map(fn (int $tax): array => Allocation::largestRemainder($tax, $weights), $line['taxes']);

            foreach ($indexes as $position => $bill) {
                $tax = array_sum(array_map(fn (array $shares): int => $shares[$position], $taxes));
                $gross = $amount[$position] - $discount[$position] + ($inclusive ? 0 : $tax);
                $bills[$bill]['amount'] += $amount[$position];
                $bills[$bill]['discount'] += $discount[$position];
                $bills[$bill]['gross'] += $gross;

                foreach ($taxes as $code => $shares) {
                    $bills[$bill]['taxes'][$code] += $shares[$position];
                }

                $bills[$bill]['lines'][] = [
                    'id' => $line['id'], 'quantity' => $this->quantity($line['quantity'], $weights[$position], array_sum($weights)),
                    'amount' => $amount[$position], 'discount' => $discount[$position], 'tax' => $tax, 'gross' => $gross,
                ];
            }
        }

        foreach ($bills as $index => $bill) {
            if ($bill['lines'] === []) {
                throw new InvalidArgumentException('Bill '.($index + 1).' has nothing on it.');
            }
        }

        return $bills;
    }

    /**
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @param  list<int>  $weights  one per bill
     * @return list<array<string, mixed>>
     */
    public function byWeights(array $lines, bool $inclusive, array $weights): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException('The order has nothing to bill.');
        }

        $codes = $this->codes($lines);
        $bills = $this->emptyBills(count($weights), $codes);
        $total = fn (string $key): int => array_sum(array_column($lines, $key));
        $taxTotals = array_combine($codes, array_map(fn (string $code): int => array_sum(array_map(fn (array $line): int => $line['taxes'][$code] ?? 0, $lines)), $codes));
        $orderTax = array_sum($taxTotals);
        $orderGross = $total('amount') - $total('discount') + ($inclusive ? 0 : $orderTax);

        // The bills' totals: gross, discount and each tax shared; the amount follows from them.
        $gross = Allocation::largestRemainder($orderGross, $weights);
        $discount = Allocation::largestRemainder($total('discount'), $weights);
        $taxes = array_map(fn (int $tax): array => Allocation::largestRemainder($tax, $weights), $taxTotals);

        foreach (array_keys($weights) as $bill) {
            $tax = array_sum(array_map(fn (array $shares): int => $shares[$bill], $taxes));
            $bills[$bill]['gross'] = $gross[$bill];
            $bills[$bill]['discount'] = $discount[$bill];
            $bills[$bill]['amount'] = $gross[$bill] + $discount[$bill] - ($inclusive ? 0 : $tax);

            foreach ($taxes as $code => $shares) {
                $bills[$bill]['taxes'][$code] = $shares[$bill];
            }
        }

        // Each bill lists every line with its share; the shares of a bill add up to the bill.
        $lineTax = array_map(fn (array $line): int => array_sum($line['taxes']), $lines);
        $cells = [
            'amount' => $this->cells(array_column($lines, 'amount'), array_column($bills, 'amount'), $weights),
            'discount' => $this->cells(array_column($lines, 'discount'), array_column($bills, 'discount'), $weights),
            'tax' => $this->cells($lineTax, array_map(fn (array $bill): int => array_sum($bill['taxes']), $bills), $weights),
        ];

        foreach (array_keys($weights) as $bill) {
            foreach ($lines as $index => $line) {
                $amount = $cells['amount'][$index][$bill];
                $lineDiscount = $cells['discount'][$index][$bill];
                $tax = $cells['tax'][$index][$bill];
                $bills[$bill]['lines'][] = [
                    'id' => $line['id'], 'quantity' => $this->quantity($line['quantity'], $weights[$bill], array_sum($weights)),
                    'amount' => $amount, 'discount' => $lineDiscount, 'tax' => $tax, 'gross' => $amount - $lineDiscount + ($inclusive ? 0 : $tax),
                ];
            }
        }

        return $bills;
    }

    /**
     * Shares each line's value between the bills by weight, so that each line's shares add up to the
     * line and each bill's shares add up to the bill's target: every line but the largest is shared
     * by weight; the largest takes what each bill still needs.
     *
     * @param  list<int>  $values  per line
     * @param  list<int>  $targets  per bill
     * @param  list<int>  $weights  per bill
     * @return list<list<int>> [line][bill]
     */
    private function cells(array $values, array $targets, array $weights): array
    {
        $largest = (int) array_search(max(array_map(abs(...), $values)), array_map(abs(...), $values), true);
        $cells = [];
        $left = $targets;

        foreach ($values as $index => $value) {
            if ($index === $largest) {
                continue;
            }

            $cells[$index] = Allocation::largestRemainder($value, $weights);

            foreach ($cells[$index] as $bill => $share) {
                $left[$bill] -= $share;
            }
        }

        $cells[$largest] = $left;
        ksort($cells);

        return array_values($cells);
    }

    /**
     * @param  list<string>  $codes
     * @return list<array<string, mixed>>
     */
    private function emptyBills(int $count, array $codes): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('A split needs at least one bill.');
        }

        return array_fill(0, $count, ['amount' => 0, 'discount' => 0, 'taxes' => array_fill_keys($codes, 0), 'gross' => 0, 'lines' => []]);
    }

    /**
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @return list<string>
     */
    private function codes(array $lines): array
    {
        return array_values(array_unique(array_merge([], ...array_map(fn (array $line): array => array_map(strval(...), array_keys($line['taxes'])), $lines))));
    }

    private function quantity(int $quantity, int $weight, int $total): string
    {
        return number_format($quantity * $weight / $total, 3, '.', '');
    }
}
