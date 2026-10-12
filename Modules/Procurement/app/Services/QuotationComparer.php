<?php

namespace Modules\Procurement\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Lines up vendors' quotations for one RFQ (Step 5.5): each quotation's total at the quantities asked, which items it
 * leaves out, the cheapest quotation among those that quote everything, and the cheapest price per item.
 */
class QuotationComparer
{
    /**
     * @param  array<int, string>  $asked  item id => quantity asked
     * @param  array<int, array<int, string>>  $quotes  quotation id => (item id => unit price)
     * @return array{totals: array<int, string>, missing: array<int, list<int>>, cheapest: int|null, best_price: array<int, int>}
     */
    public function compare(array $asked, array $quotes): array
    {
        $totals = [];
        $missing = [];
        $bestPrice = [];
        $lowest = [];

        foreach ($quotes as $quotationId => $prices) {
            $total = BigDecimal::zero();
            $missing[$quotationId] = [];

            foreach ($asked as $itemId => $quantity) {
                if (! isset($prices[$itemId])) {
                    $missing[$quotationId][] = $itemId;

                    continue;
                }

                $total = $total->plus(BigDecimal::of($quantity)->multipliedBy($prices[$itemId]));

                if (! isset($lowest[$itemId]) || BigDecimal::of($prices[$itemId])->isLessThan($lowest[$itemId])) {
                    $lowest[$itemId] = $prices[$itemId];
                    $bestPrice[$itemId] = $quotationId;
                }
            }

            $totals[$quotationId] = (string) $total->toScale(2, RoundingMode::HalfUp);
        }

        $cheapest = null;

        foreach ($totals as $quotationId => $total) {
            if ($missing[$quotationId] === [] && ($cheapest === null || BigDecimal::of($total)->isLessThan($totals[$cheapest]))) {
                $cheapest = $quotationId;
            }
        }

        return ['totals' => $totals, 'missing' => $missing, 'cheapest' => $cheapest, 'best_price' => $bestPrice];
    }
}
