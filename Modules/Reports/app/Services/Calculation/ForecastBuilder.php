<?php

namespace Modules\Reports\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reservation\DTOs\ForecastDay;

/**
 * The occupancy forecast (Step 7.1): for each coming night the rooms that can be sold, those booked or held, and the occupancy
 * on the books. Pure.
 */
final class ForecastBuilder
{
    /**
     * @param  list<ForecastDay>  $days
     */
    public function build(array $days, string $title): ReportTable
    {
        $rows = [];
        $sold = 0;
        $available = 0;
        $arrivals = 0;
        $peak = ['percent' => BigDecimal::zero(), 'date' => ''];

        foreach ($days as $day) {
            $sellable = max(0, $day->roomsTotal - $day->roomsBlocked);
            $percent = $sellable > 0 ? BigDecimal::of(min($day->roomsBooked, $sellable))->multipliedBy(100)->dividedBy($sellable, 1, RoundingMode::HalfUp) : BigDecimal::zero();
            $sold += $day->roomsBooked;
            $available += $sellable;
            $arrivals += $day->arrivals;
            $rows[] = [$day->date, CarbonImmutable::parse($day->date)->translatedFormat('D'), (string) $sellable, (string) $day->roomsBooked, $percent.'%', (string) $day->arrivals];

            if ($percent->isGreaterThan($peak['percent'])) {
                $peak = ['percent' => $percent, 'date' => $day->date];
            }
        }

        $overall = $available > 0 ? (string) BigDecimal::of($sold)->multipliedBy(100)->dividedBy($available, 1, RoundingMode::HalfUp) : '0.0';

        return new ReportTable(
            $title, [__('Night'), __('Day'), __('Rooms to sell'), __('Booked'), __('Occupancy'), __('Arrivals')], $rows,
            [__('Total'), '', (string) $available, (string) $sold, $overall.'%', (string) $arrivals],
            [['label' => __('Occupancy on the books'), 'value' => $overall.'%'], ['label' => __('Room nights booked'), 'value' => (string) $sold], ['label' => __('Busiest night'), 'value' => $peak['date'] !== '' ? $peak['date'].' ('.$peak['percent'].'%)' : '—']],
            __('Rooms to sell are the active rooms less those out of order or blocked. Booked counts reservations and holds. This is what is on the books today, not a prediction.'),
        );
    }
}
