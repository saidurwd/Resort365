<?php

namespace Modules\Reports\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reservation\DTOs\BookingFact;

/**
 * Booking pace (Step 7.1): how far ahead of arrival bookings were made. Cancelled bookings are left out. Pure.
 */
final class PaceBuilder
{
    /** @var array<string, array{0: int, 1: int|null}> */
    private const array BUCKETS = ['0–7 days' => [0, 7], '8–30 days' => [8, 30], '31–90 days' => [31, 90], 'More than 90 days' => [91, null]];

    /**
     * @param  list<BookingFact>  $bookings
     */
    public function build(array $bookings, string $title): ReportTable
    {
        $buckets = [];

        foreach (self::BUCKETS as $label => $range) {
            $buckets[$label] = ['bookings' => 0, 'nights' => 0, 'revenue' => BigDecimal::zero(), 'lead' => 0];
        }

        $count = 0;
        $leadSum = 0;
        $nights = 0;
        $revenue = BigDecimal::zero();

        foreach ($bookings as $booking) {
            if ($booking->isCancelled()) {
                continue;
            }

            $lead = $booking->leadDays();

            foreach (self::BUCKETS as $label => [$low, $high]) {
                if ($lead >= $low && ($high === null || $lead <= $high)) {
                    $buckets[$label]['bookings']++;
                    $buckets[$label]['nights'] += $booking->roomNights();
                    $buckets[$label]['revenue'] = $buckets[$label]['revenue']->plus($booking->total);
                    $buckets[$label]['lead'] += $lead;

                    break;
                }
            }

            $count++;
            $leadSum += $lead;
            $nights += $booking->roomNights();
            $revenue = $revenue->plus($booking->total);
        }

        $rows = [];

        foreach ($buckets as $label => $bucket) {
            $rows[] = [__($label), (string) $bucket['bookings'], (string) $bucket['nights'], number_format((float) (string) $bucket['revenue'], 2), $this->share($bucket['bookings'], $count), $bucket['bookings'] > 0 ? number_format($bucket['lead'] / $bucket['bookings'], 1) : '—'];
        }

        $average = $count > 0 ? number_format($leadSum / $count, 1) : '—';

        return new ReportTable(
            $title, [__('Booked ahead'), __('Bookings'), __('Room nights'), __('Revenue'), __('Share of bookings'), __('Average days ahead')], $rows,
            [__('Total'), (string) $count, (string) $nights, number_format((float) (string) $revenue, 2), $count > 0 ? '100.0%' : '—', $average],
            [['label' => __('Bookings'), 'value' => (string) $count], ['label' => __('Average days ahead'), 'value' => $average], ['label' => __('Room nights'), 'value' => (string) $nights]],
            __('Days ahead are the days between the day the booking was made and the arrival. Cancelled bookings are left out; revenue is the booking total with tax.'),
        );
    }

    private function share(int $part, int $whole): string
    {
        return $whole > 0 ? BigDecimal::of($part)->multipliedBy(100)->dividedBy($whole, 1, RoundingMode::HalfUp).'%' : '—';
    }
}
