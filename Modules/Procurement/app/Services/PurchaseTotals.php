<?php

namespace Modules\Procurement\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The arithmetic of a purchase document, in decimal strings: a line's net amount is quantity × unit price rounded
 * half-up to two places (the only rounding point), and the document adds its lines.
 */
class PurchaseTotals
{
    public function net(string $quantity, string $unitPrice): string
    {
        return (string) BigDecimal::of($quantity)->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * @param  list<array{net: string, tax: string}>  $lines
     * @return array{subtotal: string, tax_total: string, total: string}
     */
    public function summarise(array $lines): array
    {
        $subtotal = BigDecimal::zero();
        $tax = BigDecimal::zero();

        foreach ($lines as $line) {
            $subtotal = $subtotal->plus($line['net']);
            $tax = $tax->plus($line['tax']);
        }

        return ['subtotal' => (string) $subtotal->toScale(2), 'tax_total' => (string) $tax->toScale(2), 'total' => (string) $subtotal->plus($tax)->toScale(2)];
    }
}
