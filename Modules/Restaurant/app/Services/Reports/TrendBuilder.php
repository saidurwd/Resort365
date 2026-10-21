<?php

namespace Modules\Restaurant\Services\Reports;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * F&B trends (Step 7.2): net sales, covers and spend per cover by day, week (Monday to Sunday) or month for a range of dates,
 * with each row's change against the one before and the whole range against the equal range before it. Days with no sales
 * show as zero. Pure.
 */
class TrendBuilder
{
    public const array GRAINS = ['day', 'week', 'month'];

    /**
     * @param  array<string, array{sales: string, covers: int, bills: int}>  $days  by date, for the range and the equal range before it
     * @return array{
     *     rows: list<array{key: string, label: string, sales: string, covers: int, bills: int, per_cover: string, change: string|null}>,
     *     totals: array{sales: string, covers: int, bills: int, per_cover: string, previous_sales: string, change: string|null},
     *     best: array{label: string, sales: string}|null
     * }
     */
    public function build(array $days, string $from, string $to, string $grain): array
    {
        $grain = in_array($grain, self::GRAINS, true) ? $grain : 'day';
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $length = (int) $start->diffInDays($end) + 1;
        $buckets = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            [$key, $label] = $this->bucket($day, $grain);
            $buckets[$key] ??= ['label' => $label, 'sales' => BigDecimal::zero(), 'covers' => 0, 'bills' => 0];
            $fact = $days[$day->toDateString()] ?? null;

            if ($fact !== null) {
                $buckets[$key]['sales'] = $buckets[$key]['sales']->plus($fact['sales']);
                $buckets[$key]['covers'] += $fact['covers'];
                $buckets[$key]['bills'] += $fact['bills'];
            }
        }

        $rows = [];
        $previous = null;
        $best = null;
        $sales = BigDecimal::zero();
        $covers = 0;
        $bills = 0;

        foreach ($buckets as $key => $bucket) {
            $rows[] = [
                'key' => (string) $key, 'label' => $bucket['label'], 'sales' => (string) $bucket['sales']->toScale(2), 'covers' => $bucket['covers'], 'bills' => $bucket['bills'],
                'per_cover' => $this->perCover($bucket['sales'], $bucket['covers']), 'change' => $previous instanceof BigDecimal ? $this->change($bucket['sales'], $previous) : null,
            ];
            $previous = $bucket['sales'];
            $sales = $sales->plus($bucket['sales']);
            $covers += $bucket['covers'];
            $bills += $bucket['bills'];

            if ($best === null || $bucket['sales']->isGreaterThan($best['sales'])) {
                $best = ['label' => $bucket['label'], 'sales' => $bucket['sales']];
            }
        }

        $before = BigDecimal::zero();

        for ($day = $start->subDays($length); $day->lt($start); $day = $day->addDay()) {
            $before = $before->plus($days[$day->toDateString()]['sales'] ?? '0');
        }

        return [
            'rows' => $rows,
            'totals' => ['sales' => (string) $sales->toScale(2), 'covers' => $covers, 'bills' => $bills, 'per_cover' => $this->perCover($sales, $covers), 'previous_sales' => (string) $before->toScale(2), 'change' => $before->isZero() ? null : $this->change($sales, $before)],
            'best' => $best !== null && $best['sales']->isPositive() ? ['label' => $best['label'], 'sales' => (string) $best['sales']->toScale(2)] : null,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function bucket(CarbonImmutable $day, string $grain): array
    {
        return match ($grain) {
            'week' => [$day->startOfWeek()->toDateString(), $day->startOfWeek()->format('d M').' – '.$day->endOfWeek()->format('d M')],
            'month' => [$day->format('Y-m'), $day->format('F Y')],
            default => [$day->toDateString(), $day->format('D d M')],
        };
    }

    private function perCover(BigDecimal $sales, int $covers): string
    {
        return $covers > 0 ? (string) $sales->dividedBy($covers, 2, RoundingMode::HalfUp) : '0.00';
    }

    private function change(BigDecimal $now, BigDecimal $before): ?string
    {
        return $before->isZero() ? null : (string) $now->minus($before)->multipliedBy(100)->dividedBy($before, 1, RoundingMode::HalfUp);
    }
}
