<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\DTOs\TaxLine;

/**
 * The tax breakdown (tax name => amount) of a charge for its folio line and the invoice. Room
 * nights carry the tax total frozen at booking; the breakdown is worked out again from the net and
 * reconciled to that total (a rounding difference goes to the last tax), so lines always add up.
 */
class TaxSplitter
{
    public function __construct(private readonly TaxEngine $taxes) {}

    /**
     * @return array<string, string>
     */
    public function split(string $net, ?int $taxCategoryId, string $taxTotal): array
    {
        if (BigDecimal::of($taxTotal)->isZero()) {
            return [];
        }

        $lines = $this->taxes->calculate($net, $taxCategoryId)->taxes;

        if ($lines === []) {
            return [__('Tax') => (string) BigDecimal::of($taxTotal)->toScale(2)];
        }

        $split = [];

        foreach ($lines as $line) {
            /** @var TaxLine $line */
            $split[$line->name] = (string) BigDecimal::of($split[$line->name] ?? '0')->plus($line->amount)->toScale(2);
        }

        $difference = BigDecimal::of($taxTotal)->minus(array_reduce($split, fn (BigDecimal $sum, string $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero()));
        $last = array_key_last($split);
        $split[$last] = (string) BigDecimal::of($split[$last])->plus($difference)->toScale(2);

        return $split;
    }

    /**
     * Adds breakdowns together (for an invoice).
     *
     * @param  iterable<array<string, string>|null>  $breakdowns
     * @return array<string, string>
     */
    public function sum(iterable $breakdowns): array
    {
        $total = [];

        foreach ($breakdowns as $breakdown) {
            foreach ($breakdown ?? [] as $name => $amount) {
                $total[$name] = (string) BigDecimal::of($total[$name] ?? '0')->plus($amount)->toScale(2);
            }
        }

        return $total;
    }
}
