<?php

namespace Modules\Reports\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reservation\DTOs\BookingFact;

/**
 * Length of stay (Step 7.1): how many bookings stay one night, two, three and so on, and the average stay. Cancelled bookings and
 * no-shows are left out. Pure.
 */
final class LengthOfStayBuilder
{
    /** @var array<string, array{0: int, 1: int|null}> */
    private const array BUCKETS = ['1 night' => [1, 1], '2 nights' => [2, 2], '3 nights' => [3, 3], '4 nights' => [4, 4], '5 nights' => [5, 5], '6–7 nights' => [6, 7], '8–14 nights' => [8, 14], '15 nights or more' => [15, null]];

    /**
     * @param  list<BookingFact>  $bookings
     */
    public function build(array $bookings, string $title): ReportTable
    {
        $buckets = [];

        foreach (array_keys(self::BUCKETS) as $label) {
            $buckets[$label] = ['bookings' => 0, 'nights' => 0];
        }

        $count = 0;
        $nights = 0;
        $roomNights = 0;

        foreach ($bookings as $booking) {
            if ($booking->isCancelled() || $booking->status === 'no_show' || $booking->nights < 1) {
                continue;
            }

            foreach (self::BUCKETS as $label => [$low, $high]) {
                if ($booking->nights >= $low && ($high === null || $booking->nights <= $high)) {
                    $buckets[$label]['bookings']++;
                    $buckets[$label]['nights'] += $booking->roomNights();

                    break;
                }
            }

            $count++;
            $nights += $booking->nights;
            $roomNights += $booking->roomNights();
        }

        $rows = [];

        foreach ($buckets as $label => $bucket) {
            $rows[] = [__($label), (string) $bucket['bookings'], (string) $bucket['nights'], $count > 0 ? BigDecimal::of($bucket['bookings'])->multipliedBy(100)->dividedBy($count, 1, RoundingMode::HalfUp).'%' : '—'];
        }

        $average = $count > 0 ? number_format($nights / $count, 2) : '—';

        return new ReportTable(
            $title, [__('Length of stay'), __('Bookings'), __('Room nights'), __('Share of bookings')], $rows, [__('Total'), (string) $count, (string) $roomNights, $count > 0 ? '100.0%' : '—'],
            [['label' => __('Average length of stay'), 'value' => $average], ['label' => __('Bookings'), 'value' => (string) $count], ['label' => __('Room nights'), 'value' => (string) $roomNights]],
            __('Cancelled bookings and no-shows are left out. A booking of several rooms counts once, with its nights times its rooms as room nights.'),
        );
    }
}
