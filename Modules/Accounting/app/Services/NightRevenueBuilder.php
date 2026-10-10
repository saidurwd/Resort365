<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Modules\Billing\DTOs\ChargeFact;

/**
 * Adds a property day's folio charges up for the ledger (ARCHITECTURE §7.1): revenue by charge code,
 * taxes by name, and the total the guest ledger is debited with. The meal part of a room night
 * (package rate) is moved to F&B revenue. Pure; amounts are decimal strings and adjustments may be
 * negative.
 */
class NightRevenueBuilder
{
    /**
     * @param  list<ChargeFact>  $charges
     * @return array{revenue: array<string, array{code: ?string, category: string, amount: string}>, taxes: array<string, string>, total: string}
     */
    public function build(array $charges): array
    {
        $revenue = [];
        $taxes = [];
        $total = BigDecimal::zero();

        foreach ($charges as $charge) {
            $meal = BigDecimal::of($charge->mealAmount);
            $net = BigDecimal::of($charge->amount);

            if ($meal->isZero() || $charge->category !== 'room') {
                $this->add($revenue, $charge->chargeCode, $charge->category, $net);
            } else {
                $this->add($revenue, $charge->chargeCode, $charge->category, $net->minus($meal));
                $this->add($revenue, 'FNB', 'food_beverage', $meal);
            }

            $total = $total->plus($net);
            $lines = $charge->taxLines;

            if ($lines === [] && ! BigDecimal::of($charge->taxAmount)->isZero()) {
                $lines = ['VAT' => $charge->taxAmount];
            }

            foreach ($lines as $name => $amount) {
                $taxes[$name] = (string) BigDecimal::of($taxes[$name] ?? '0')->plus($amount)->toScale(2);
                $total = $total->plus($amount);
            }
        }

        return ['revenue' => array_filter($revenue, fn (array $row): bool => ! BigDecimal::of($row['amount'])->isZero()), 'taxes' => array_filter($taxes, fn (string $amount): bool => ! BigDecimal::of($amount)->isZero()), 'total' => (string) $total->toScale(2)];
    }

    /**
     * @param  array<string, array{code: ?string, category: string, amount: string}>  $revenue
     */
    private function add(array &$revenue, ?string $code, string $category, BigDecimal $amount): void
    {
        $key = ($code ?? '').'|'.$category;
        $revenue[$key] = ['code' => $code, 'category' => $category, 'amount' => (string) BigDecimal::of($revenue[$key]['amount'] ?? '0')->plus($amount)->toScale(2)];
    }
}
