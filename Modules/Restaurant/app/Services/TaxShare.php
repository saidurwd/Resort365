<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The part of a bill's taxes in a payment charged elsewhere (a room or a company account, Step 3.7),
 * without the database: each tax in proportion to the amount (half-up); the rest of the amount is the
 * net. A payment of the whole bill takes its taxes exactly.
 */
class TaxShare
{
    /**
     * @param  list<array{code: string, name: string, rate: string, amount: string}>  $breakdown  the bill's taxes
     * @return array{net: string, taxes: array<string, string>} taxes by name
     */
    public function of(array $breakdown, string $grandTotal, string $amount): array
    {
        $whole = BigDecimal::of($amount)->isEqualTo($grandTotal);
        $taxes = [];

        foreach ($breakdown as $tax) {
            $share = $whole || BigDecimal::of($grandTotal)->isZero() ? BigDecimal::of($tax['amount'])
                : BigDecimal::of($tax['amount'])->multipliedBy($amount)->dividedBy($grandTotal, 2, RoundingMode::HalfUp);
            $taxes[$tax['name']] = (string) BigDecimal::of($taxes[$tax['name']] ?? '0')->plus($share)->toScale(2);
        }

        $total = array_reduce($taxes, fn (BigDecimal $sum, string $tax): BigDecimal => $sum->plus($tax), BigDecimal::zero());

        return ['net' => (string) BigDecimal::of($amount)->minus($total)->toScale(2), 'taxes' => $taxes];
    }
}
