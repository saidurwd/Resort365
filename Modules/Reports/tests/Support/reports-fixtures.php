<?php

use Modules\FrontOffice\DTOs\StatTotals;
use Modules\Reservation\DTOs\BookingFact;

/**
 * A booking fact with sensible defaults: two nights from 10 March 2026, one room, booked at the front desk ten days earlier.
 *
 * @param  array<string, mixed>  $overrides
 */
function rpFact(array $overrides = []): BookingFact
{
    return BookingFact::from([
        'reservationId' => 1, 'code' => 'RSV-1', 'propertyId' => 1, 'source' => 'front_desk', 'status' => 'checked_out', 'bookedOn' => '2026-02-28', 'checkIn' => '2026-03-10', 'checkOut' => '2026-03-12', 'nights' => 2, 'rooms' => 1,
        'total' => '10000.00', 'cancellationFee' => '0.00', 'cancelledOn' => null, 'guestId' => 1, 'companyId' => null, 'travelAgentId' => null, 'adults' => 2, 'children' => 0, ...$overrides,
    ]);
}

/**
 * Night audit sums with defaults for what a test does not care about.
 *
 * @param  array<string, mixed>  $overrides
 */
function rpTotals(array $overrides = []): StatTotals
{
    return StatTotals::from([
        'days' => 1, 'roomsAvailable' => 15, 'roomsOccupied' => 9, 'roomRevenue' => '54000.00', 'packageMealRevenue' => '0.00', 'fnbSales' => '12000.00', 'fnbCovers' => 18, 'received' => '40000.00', 'refunded' => '0.00',
        'arrivals' => 3, 'departures' => 2, 'noShows' => 0, 'adults' => 18, 'children' => 2, ...$overrides,
    ]);
}
