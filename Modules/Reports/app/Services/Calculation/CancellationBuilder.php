<?php

namespace Modules\Reports\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reservation\DTOs\BookingFact;
use Modules\Reservation\Enums\ReservationSource;

/**
 * Cancellations and no-shows (Step 7.1), one row per source: the bookings, how many were cancelled and the rate, no-shows, the
 * fees kept, and how many days before arrival the cancellations came. Pure.
 */
final class CancellationBuilder
{
    /**
     * @param  list<BookingFact>  $bookings
     */
    public function build(array $bookings, string $title): ReportTable
    {
        $sources = [];
        $all = ['bookings' => 0, 'cancelled' => 0, 'noshow' => 0, 'fees' => BigDecimal::zero(), 'notice' => 0];

        foreach ($bookings as $booking) {
            $row = $sources[$booking->source] ?? ['bookings' => 0, 'cancelled' => 0, 'noshow' => 0, 'fees' => BigDecimal::zero(), 'notice' => 0];
            $row['bookings']++;
            $all['bookings']++;

            if ($booking->isCancelled()) {
                $notice = $booking->cancelledOn !== null ? max(0, (int) round((strtotime($booking->checkIn) - strtotime($booking->cancelledOn)) / 86400)) : 0;
                $row['cancelled']++;
                $row['fees'] = $row['fees']->plus($booking->cancellationFee);
                $row['notice'] += $notice;
                $all['cancelled']++;
                $all['fees'] = $all['fees']->plus($booking->cancellationFee);
                $all['notice'] += $notice;
            } elseif ($booking->status === 'no_show') {
                $row['noshow']++;
                $all['noshow']++;
            }

            $sources[$booking->source] = $row;
        }

        ksort($sources);
        $rows = [];

        foreach ($sources as $source => $row) {
            $rows[] = [ReservationSource::tryFrom($source)?->label() ?? $source, ...$this->cells($row)];
        }

        return new ReportTable(
            $title, [__('Source'), __('Bookings'), __('Cancelled'), __('Cancellation rate'), __('No-shows'), __('Fees kept'), __('Average days before arrival')], $rows,
            [__('Total'), ...$this->cells($all)],
            [['label' => __('Cancelled'), 'value' => (string) $all['cancelled']], ['label' => __('Cancellation rate'), 'value' => $this->rate($all['cancelled'], $all['bookings'])], ['label' => __('No-shows'), 'value' => (string) $all['noshow']], ['label' => __('Fees kept'), 'value' => number_format((float) (string) $all['fees'], 2)]],
            __('The rate is cancelled bookings as a share of the bookings. Days before arrival are counted from the day of cancellation to the arrival date.'),
        );
    }

    /**
     * @param  array{bookings: int, cancelled: int, noshow: int, fees: BigDecimal, notice: int}  $row
     * @return list<string>
     */
    private function cells(array $row): array
    {
        return [(string) $row['bookings'], (string) $row['cancelled'], $this->rate($row['cancelled'], $row['bookings']), (string) $row['noshow'], number_format((float) (string) $row['fees'], 2), $row['cancelled'] > 0 ? number_format($row['notice'] / $row['cancelled'], 1) : '—'];
    }

    private function rate(int $part, int $whole): string
    {
        return $whole > 0 ? BigDecimal::of($part)->multipliedBy(100)->dividedBy($whole, 1, RoundingMode::HalfUp).'%' : '—';
    }
}
