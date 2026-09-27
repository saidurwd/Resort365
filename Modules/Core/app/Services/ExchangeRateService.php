<?php

namespace Modules\Core\Services;

use DateTimeInterface;
use Modules\Core\Contracts\ExchangeRates;
use Modules\Core\Models\ExchangeRate;

class ExchangeRateService implements ExchangeRates
{
    private const int SCALE = 8;

    public function rate(string $from, string $to, ?DateTimeInterface $date = null): ?string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return '1.00000000';
        }

        $day = ($date ?? now())->format('Y-m-d');

        $direct = $this->latest($from, $to, $day);

        if ($direct !== null) {
            return bcadd($direct, '0', self::SCALE);
        }

        $inverse = $this->latest($to, $from, $day);

        return $inverse !== null && bccomp($inverse, '0', self::SCALE) === 1
            ? bcdiv('1', $inverse, self::SCALE)
            : null;
    }

    /**
     * @return numeric-string|null
     */
    private function latest(string $base, string $quote, string $day): ?string
    {
        $rate = ExchangeRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->whereDate('effective_date', '<=', $day)
            ->latest('effective_date')
            ->value('rate');

        return is_numeric($rate) ? (string) $rate : null;
    }
}
