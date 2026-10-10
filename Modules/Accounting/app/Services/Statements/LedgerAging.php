<?php

namespace Modules\Accounting\Services\Statements;

use Carbon\CarbonImmutable;

/**
 * Ages the open balance of a control account per party (Step 4.5): payments and credits are applied to the oldest
 * charges first, and what is left of each charge falls into 0–30, 31–60, 61–90 or over 90 days old on the date.
 * Money received beyond the charges is shown as a credit. Pure; amounts are decimal strings, a charge positive.
 */
class LedgerAging
{
    public const array BUCKETS = ['current', 'days_31_60', 'days_61_90', 'over_90'];

    /**
     * @param  list<array{party: string, date: string, amount: string}>  $movements  signed so that a charge is positive and a settlement negative
     * @return array<string, array{current: string, days_31_60: string, days_61_90: string, over_90: string, credit: string, total: string}>
     */
    public function build(array $movements, string $asOf): array
    {
        $byParty = [];

        foreach ($movements as $movement) {
            $byParty[$movement['party']][] = $movement;
        }

        $result = [];

        foreach ($byParty as $party => $items) {
            usort($items, fn (array $a, array $b): int => $a['date'] <=> $b['date']);
            $charges = [];
            $settled = '0.00';

            foreach ($items as $item) {
                bccomp($item['amount'], '0', 2) >= 0 ? $charges[] = $item : $settled = bcadd($settled, ltrim($item['amount'], '-'), 2);
            }

            $row = array_fill_keys(self::BUCKETS, '0.00') + ['credit' => '0.00', 'total' => '0.00'];

            foreach ($charges as $charge) {
                $open = $charge['amount'];

                if (bccomp($settled, '0', 2) > 0) {
                    $used = bccomp($settled, $open, 2) >= 0 ? $open : $settled;
                    $settled = bcsub($settled, $used, 2);
                    $open = bcsub($open, $used, 2);
                }

                if (bccomp($open, '0', 2) > 0) {
                    $bucket = $this->bucket((int) CarbonImmutable::parse($charge['date'])->diffInDays(CarbonImmutable::parse($asOf)));
                    $row[$bucket] = bcadd($row[$bucket], $open, 2);
                }
            }

            $row['credit'] = $settled;
            $row['total'] = bcsub(bcadd(bcadd(bcadd($row['current'], $row['days_31_60'], 2), $row['days_61_90'], 2), $row['over_90'], 2), $row['credit'], 2);

            if (bccomp($row['total'], '0', 2) !== 0 || bccomp($row['credit'], '0', 2) !== 0) {
                $result[$party] = $row;
            }
        }

        ksort($result);

        return $result;
    }

    private function bucket(int $days): string
    {
        return match (true) {
            $days <= 30 => 'current',
            $days <= 60 => 'days_31_60',
            $days <= 90 => 'days_61_90',
            default => 'over_90',
        };
    }
}
