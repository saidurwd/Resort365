<?php

namespace Modules\Reports\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reservation\DTOs\BookingFact;
use Modules\Reservation\Enums\ReservationSource;

/**
 * Where bookings come from (Step 7.1), one row per source: bookings, cancellations, room nights and revenue, with each source's
 * share of the revenue. Cancelled bookings bring no nights or revenue. Pure.
 */
final class SourceMixBuilder
{
    /**
     * @param  list<BookingFact>  $bookings
     */
    public function build(array $bookings, string $title): ReportTable
    {
        $sources = [];

        foreach ($bookings as $booking) {
            $row = $sources[$booking->source] ?? ['bookings' => 0, 'cancelled' => 0, 'nights' => 0, 'revenue' => BigDecimal::zero()];
            $row['bookings']++;

            if ($booking->isCancelled()) {
                $row['cancelled']++;
            } else {
                $row['nights'] += $booking->roomNights();
                $row['revenue'] = $row['revenue']->plus($booking->total);
            }

            $sources[$booking->source] = $row;
        }

        uasort($sources, fn (array $a, array $b): int => $b['revenue']->compareTo($a['revenue']) ?: $b['bookings'] <=> $a['bookings']);
        $revenue = array_reduce($sources, fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['revenue']), BigDecimal::zero());
        $rows = [];

        foreach ($sources as $source => $row) {
            $rows[] = [ReservationSource::tryFrom($source)?->label() ?? $source, (string) $row['bookings'], (string) $row['cancelled'], (string) $row['nights'], number_format((float) (string) $row['revenue'], 2), $this->average($row['revenue'], $row['bookings'] - $row['cancelled']), $revenue->isZero() ? '0.0%' : $row['revenue']->multipliedBy(100)->dividedBy($revenue, 1, RoundingMode::HalfUp).'%'];
        }

        $count = array_sum(array_column($sources, 'bookings'));
        $cancelled = array_sum(array_column($sources, 'cancelled'));

        return new ReportTable(
            $title, [__('Source'), __('Bookings'), __('Cancelled'), __('Room nights'), __('Revenue'), __('Average booking'), __('Share of revenue')], $rows,
            [__('Total'), (string) $count, (string) $cancelled, (string) array_sum(array_column($sources, 'nights')), number_format((float) (string) $revenue, 2), $this->average($revenue, $count - $cancelled), $count > 0 ? '100.0%' : '—'],
            [['label' => __('Bookings'), 'value' => (string) $count], ['label' => __('Cancelled'), 'value' => (string) $cancelled], ['label' => __('Revenue'), 'value' => number_format((float) (string) $revenue, 2)]],
            __('Cancelled bookings are counted but bring no room nights or revenue. Revenue is the booking total with tax.'),
        );
    }

    private function average(BigDecimal $revenue, int $bookings): string
    {
        return $bookings > 0 ? number_format((float) (string) $revenue->dividedBy($bookings, 2, RoundingMode::HalfUp), 2) : '0.00';
    }
}
