<?php

namespace Modules\Procurement\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Splits a return into the part that was never billed (it reverses the goods received not invoiced) and the part that
 * was (it reduces the payable, with its tax), Step 5.6. Returned goods come out of the unbilled stock first.
 */
class ReturnSplitter
{
    /**
     * @param  string  $taxRatio  the order line's tax over its net amount (0.15 for 15%)
     * @return array{value: string, grni_value: string, billed_value: string, tax_value: string, grni_quantity: string, billed_quantity: string}
     */
    public function split(string $quantity, string $received, string $alreadyReturned, string $billed, string $unitCost, string $taxRatio): array
    {
        $unbilled = BigDecimal::of($received)->minus($alreadyReturned)->minus($billed);
        $unbilled = $unbilled->isNegative() ? BigDecimal::zero() : $unbilled;
        $grniQuantity = BigDecimal::of($quantity)->isLessThan($unbilled) ? BigDecimal::of($quantity) : $unbilled;
        $billedQuantity = BigDecimal::of($quantity)->minus($grniQuantity);
        $value = BigDecimal::of($quantity)->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp);
        $grni = $grniQuantity->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp);
        $billedValue = $value->minus($grni);

        return [
            'value' => (string) $value, 'grni_value' => (string) $grni, 'billed_value' => (string) $billedValue,
            'tax_value' => (string) $billedValue->multipliedBy($taxRatio)->toScale(2, RoundingMode::HalfUp),
            'grni_quantity' => (string) $grniQuantity->toScale(4), 'billed_quantity' => (string) $billedQuantity->toScale(4),
        ];
    }
}
