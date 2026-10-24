<?php

namespace Modules\Website\Services;

/**
 * Money as the website shows it (Step 8.2a): "BDT 6,000" (no decimals when there are none, "BDT 6,000.50" otherwise). Amounts stay
 * decimal strings; this only formats them.
 */
final class Prices
{
    public static function format(string $amount, string $currency): string
    {
        [$whole, $cents] = array_pad(explode('.', ltrim($amount, '-')), 2, '00');
        $grouped = number_format((float) $whole, 0, '.', ',');
        $cents = str_pad(substr($cents, 0, 2), 2, '0');

        return ($amount !== '' && $amount[0] === '-' ? '-' : '').$currency.' '.$grouped.($cents === '00' ? '' : '.'.$cents);
    }

    /**
     * A decimal string without needless zeros: "30.00" → "30", "12.50" → "12.5", "100" stays "100".
     */
    public static function number(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
