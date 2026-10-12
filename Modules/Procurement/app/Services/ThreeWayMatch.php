<?php

namespace Modules\Procurement\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The 3-way match (Step 5.6): an invoice line is checked against what the order said and what was received. The
 * quantity may not pass what is received, less what was returned and already billed, by more than the quantity
 * tolerance; a price above the order price by more than the price tolerance needs an override. Tolerances are percents.
 */
class ThreeWayMatch
{
    /**
     * @param  array<int|string, array{billed: string, price: string, received: string, returned: string, already_billed: string, po_price: string}>  $lines
     * @return array{lines: array<int|string, array{allowed: string, excess: string, quantity_ok: bool, price_limit: string, price_ok: bool}>, quantity_blocked: bool, price_flagged: bool}
     */
    public function check(array $lines, string $quantityTolerance, string $priceTolerance): array
    {
        $result = [];
        $quantityBlocked = false;
        $priceFlagged = false;

        foreach ($lines as $key => $line) {
            $open = BigDecimal::of($line['received'])->minus($line['returned'])->minus($line['already_billed']);
            $open = $open->isNegative() ? BigDecimal::zero() : $open;
            $allowed = $open->multipliedBy(BigDecimal::of('100')->plus($quantityTolerance))->dividedBy('100', 4, RoundingMode::Down);
            $excess = BigDecimal::of($line['billed'])->minus($allowed);
            $limit = BigDecimal::of($line['po_price'])->multipliedBy(BigDecimal::of('100')->plus($priceTolerance))->dividedBy('100', 4, RoundingMode::Up);
            $quantityOk = ! $excess->isPositive();
            $priceOk = ! BigDecimal::of($line['price'])->isGreaterThan($limit);
            $quantityBlocked = $quantityBlocked || ! $quantityOk;
            $priceFlagged = $priceFlagged || ! $priceOk;
            $result[$key] = ['allowed' => (string) $allowed->toScale(4), 'excess' => (string) ($excess->isPositive() ? $excess : BigDecimal::zero())->toScale(4), 'quantity_ok' => $quantityOk, 'price_limit' => (string) $limit, 'price_ok' => $priceOk];
        }

        return ['lines' => $result, 'quantity_blocked' => $quantityBlocked, 'price_flagged' => $priceFlagged];
    }
}
