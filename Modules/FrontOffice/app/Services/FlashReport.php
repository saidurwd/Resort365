<?php

namespace Modules\FrontOffice\Services;

use Modules\Billing\Contracts\DailyTakings;
use Modules\FrontOffice\Models\DailyStatistic;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Contracts\ReservationLookup;

/**
 * The daily flash report (ARCHITECTURE §8 Finance, "daily revenue report"): a closed day comes from
 * its night-audit snapshot (DailyStatistic); the current business date is calculated live the same
 * way and marked provisional. Other dates have no report.
 */
class FlashReport
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly DailyTakings $takings,
        private readonly InventoryCatalog $catalog,
        private readonly DailyStatsCalculator $stats,
    ) {}

    /**
     * @return array{provisional: bool, stats: array<string, mixed>, takings: array<string, mixed>}|null
     */
    public function for(int $propertyId, string $date, string $businessDate): ?array
    {
        $snapshot = DailyStatistic::query()->where('property_id', $propertyId)->where('business_date', $date)->first();

        if ($snapshot instanceof DailyStatistic) {
            return ['provisional' => false, 'stats' => $snapshot->attributesToArray(), 'takings' => $snapshot->takings];
        }

        if ($date !== $businessDate) {
            return null;
        }

        $night = $this->reservations->occupancy($propertyId, $date);
        $takings = $this->takings->forDate($propertyId, $date);
        $roomsTotal = count(array_filter($this->catalog->rooms($propertyId), fn (RoomSummary $room): bool => $room->isActive));

        return [
            'provisional' => true,
            'stats' => [
                'rooms_total' => $roomsTotal, 'rooms_out_of_order' => $night->roomsOutOfOrder, 'rooms_blocked' => $night->roomsBlocked,
                'rooms_occupied' => $night->roomsOccupied, ...$this->stats->calculate($roomsTotal, $night->roomsOutOfOrder, $night->roomsOccupied, $night->roomRevenue),
                'room_revenue' => $night->roomRevenue, 'package_meal_revenue' => $night->packageMealRevenue, 'room_tax' => $night->roomTax,
                'charges_total' => $takings->chargesTotal, 'received_total' => $takings->receivedTotal, 'refunded_total' => $takings->refundedTotal,
                'adults' => $night->adults, 'children' => $night->children, 'arrivals' => $night->arrivals, 'departures' => $night->departures,
                'no_shows' => $night->noShows, 'fnb_covers' => 0, 'fnb_sales' => '0.00',
            ],
            'takings' => $takings->toArray(),
        ];
    }
}
