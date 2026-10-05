<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;

/**
 * An order line's money (ARCHITECTURE §5.10.2, §5.10.7), without the database: the modifiers chosen add
 * their prices to one portion; the line is (unit price + modifiers) × quantity. Amounts are decimal
 * strings, 2 decimals; taxes come with the bill (Step 3.6).
 */
class OrderLinePricing
{
    /**
     * @param  list<string>  $modifierPrices  the price each chosen modifier adds to one portion
     * @return array{modifier_total: string, line_total: string}
     */
    public function price(string $unitPrice, int $quantity, array $modifierPrices): array
    {
        $modifiers = array_reduce($modifierPrices, fn (BigDecimal $sum, string $price): BigDecimal => $sum->plus($price), BigDecimal::zero());

        return [
            'modifier_total' => (string) $modifiers->toScale(2),
            'line_total' => (string) BigDecimal::of($unitPrice)->plus($modifiers)->multipliedBy(max(1, $quantity))->toScale(2),
        ];
    }

    /**
     * The total of lines (voided lines left out by the caller).
     *
     * @param  list<string>  $lineTotals
     */
    public function subtotal(array $lineTotals): string
    {
        return (string) array_reduce($lineTotals, fn (BigDecimal $sum, string $total): BigDecimal => $sum->plus($total), BigDecimal::zero())->toScale(2);
    }
}
