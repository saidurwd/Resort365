<?php

namespace Modules\Core\Contracts;

use DateTimeInterface;

/**
 * Tenant exchange rates. Rates are decimal strings (18,8); never floats.
 */
interface ExchangeRates
{
    /**
     * Units of $to for 1 unit of $from on $date (latest rate effective on or before it),
     * using the inverse pair when only that exists. Null when no rate is known.
     */
    public function rate(string $from, string $to, ?DateTimeInterface $date = null): ?string;
}
