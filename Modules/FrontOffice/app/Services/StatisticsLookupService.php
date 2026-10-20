<?php

namespace Modules\FrontOffice\Services;

use Modules\FrontOffice\Contracts\StatisticsLookup;
use Modules\FrontOffice\DTOs\DailyStatFact;
use Modules\FrontOffice\DTOs\StatTotals;
use Modules\FrontOffice\Models\DailyStatistic;

class StatisticsLookupService implements StatisticsLookup
{
    public function between(array $propertyIds, string $from, string $to): array
    {
        return DailyStatistic::query()->whereIn('property_id', $propertyIds)->whereBetween('business_date', [$from, $to])->orderBy('business_date')->orderBy('property_id')
            ->get()->map(fn (DailyStatistic $row): DailyStatFact => new DailyStatFact(
                $row->property_id, $row->business_date->toDateString(), $row->rooms_available, $row->rooms_occupied, (string) $row->occupancy_percent, (string) $row->adr, (string) $row->revpar, (string) $row->room_revenue,
                (string) $row->package_meal_revenue, (string) $row->fnb_sales, $row->fnb_covers, (string) $row->received_total, (string) $row->refunded_total, $row->arrivals, $row->departures, $row->no_shows, $row->adults, $row->children,
            ))->all();
    }

    public function totals(array $propertyIds, string $from, string $to): StatTotals
    {
        $row = DailyStatistic::query()->whereIn('property_id', $propertyIds)->whereBetween('business_date', [$from, $to])
            ->selectRaw('COUNT(*) as days, COALESCE(SUM(rooms_available), 0) as rooms_available, COALESCE(SUM(rooms_occupied), 0) as rooms_occupied, COALESCE(SUM(room_revenue), 0) as room_revenue, COALESCE(SUM(package_meal_revenue), 0) as package_meal_revenue, COALESCE(SUM(fnb_sales), 0) as fnb_sales, COALESCE(SUM(fnb_covers), 0) as fnb_covers, COALESCE(SUM(received_total), 0) as received, COALESCE(SUM(refunded_total), 0) as refunded, COALESCE(SUM(arrivals), 0) as arrivals, COALESCE(SUM(departures), 0) as departures, COALESCE(SUM(no_shows), 0) as no_shows, COALESCE(SUM(adults), 0) as adults, COALESCE(SUM(children), 0) as children')
            ->toBase()->first();

        return new StatTotals(
            (int) ($row->days ?? 0), (int) ($row->rooms_available ?? 0), (int) ($row->rooms_occupied ?? 0), bcadd((string) ($row->room_revenue ?? 0), '0', 2), bcadd((string) ($row->package_meal_revenue ?? 0), '0', 2), bcadd((string) ($row->fnb_sales ?? 0), '0', 2),
            (int) ($row->fnb_covers ?? 0), bcadd((string) ($row->received ?? 0), '0', 2), bcadd((string) ($row->refunded ?? 0), '0', 2), (int) ($row->arrivals ?? 0), (int) ($row->departures ?? 0), (int) ($row->no_shows ?? 0), (int) ($row->adults ?? 0), (int) ($row->children ?? 0),
        );
    }

    public function latestDate(array $propertyIds): ?string
    {
        $date = DailyStatistic::query()->whereIn('property_id', $propertyIds)->max('business_date');

        return is_string($date) ? substr($date, 0, 10) : null;
    }
}
