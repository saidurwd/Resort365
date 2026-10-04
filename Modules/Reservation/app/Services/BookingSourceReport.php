<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Reservation\Enums\ReservationSource;

/**
 * Where bookings come from (Step 1.8), without database access. Cancelled bookings are counted
 * but bring no revenue and no nights. Nights are unit-nights: a whole cottage counts once.
 * Amounts are decimal strings; share is the percent of revenue, one decimal.
 */
class BookingSourceReport
{
    /**
     * @param  iterable<array{source: ReservationSource, cancelled: bool, nights: int, total: string}>  $bookings
     * @return array{rows: list<array{source: ReservationSource, bookings: int, cancelled: int, nights: int, revenue: string, average: string, share: string}>,
     *               totals: array{bookings: int, cancelled: int, nights: int, revenue: string, average: string}}
     */
    public function summarise(iterable $bookings): array
    {
        $sources = [];

        foreach ($bookings as $booking) {
            $row = $sources[$booking['source']->value] ??= ['source' => $booking['source'], 'bookings' => 0, 'cancelled' => 0, 'nights' => 0, 'revenue' => BigDecimal::zero()];
            $row['bookings']++;

            if ($booking['cancelled']) {
                $row['cancelled']++;
            } else {
                $row['nights'] += $booking['nights'];
                $row['revenue'] = $row['revenue']->plus($booking['total']);
            }

            $sources[$booking['source']->value] = $row;
        }

        $revenue = array_reduce($sources, fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['revenue']), BigDecimal::zero());
        $rows = [];

        foreach ($sources as $row) {
            $rows[] = [
                'source' => $row['source'],
                'bookings' => $row['bookings'],
                'cancelled' => $row['cancelled'],
                'nights' => $row['nights'],
                'revenue' => (string) $row['revenue']->toScale(2),
                'average' => $this->average($row['revenue'], $row['bookings'] - $row['cancelled']),
                'share' => $revenue->isZero() ? '0.0' : (string) $row['revenue']->multipliedBy(100)->dividedBy($revenue, 1, RoundingMode::HalfUp),
            ];
        }

        usort($rows, fn (array $a, array $b): int => BigDecimal::of($b['revenue'])->compareTo($a['revenue']) ?: $b['bookings'] <=> $a['bookings']);

        $bookingsCount = array_sum(array_column($rows, 'bookings'));
        $cancelled = array_sum(array_column($rows, 'cancelled'));

        return ['rows' => $rows, 'totals' => [
            'bookings' => $bookingsCount,
            'cancelled' => $cancelled,
            'nights' => array_sum(array_column($rows, 'nights')),
            'revenue' => (string) $revenue->toScale(2),
            'average' => $this->average($revenue, $bookingsCount - $cancelled),
        ]];
    }

    private function average(BigDecimal $revenue, int $bookings): string
    {
        return $bookings > 0 ? (string) $revenue->dividedBy($bookings, 2, RoundingMode::HalfUp) : '0.00';
    }
}
