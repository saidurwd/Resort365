<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

/**
 * City-ledger aging (ARCHITECTURE §5.9) without database access: open amounts by how many days
 * past their due date they are on a given day. Amounts are decimal strings.
 */
class AgingCalculator
{
    public const array BUCKETS = ['current', '1_30', '31_60', '61_90', 'over_90'];

    /**
     * @param  iterable<array{open: string, due_on: CarbonInterface}>  $entries
     * @return array<string, string> bucket => amount (every bucket present), plus 'total'
     */
    public function buckets(iterable $entries, CarbonInterface $asOf): array
    {
        $sums = array_fill_keys(self::BUCKETS, BigDecimal::zero());

        foreach ($entries as $entry) {
            $bucket = $this->bucket((int) $entry['due_on']->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay(), false));
            $sums[$bucket] = $sums[$bucket]->plus($entry['open']);
        }

        $total = array_reduce($sums, fn (BigDecimal $carry, BigDecimal $sum): BigDecimal => $carry->plus($sum), BigDecimal::zero());

        return [...array_map(fn (BigDecimal $sum): string => (string) $sum->toScale(2), $sums), 'total' => (string) $total->toScale(2)];
    }

    /**
     * The bucket for an amount this many days past due (0 or less = not due yet).
     */
    public function bucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'current',
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => 'over_90',
        };
    }
}
