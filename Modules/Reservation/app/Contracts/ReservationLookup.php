<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\DTOs\ReservationSummary;

/**
 * Reservations for other modules (Billing). Lookups respect the user's property access.
 */
interface ReservationLookup
{
    public function find(int $reservationId): ?ReservationSummary;

    /**
     * Bookings arriving on the date that are not checked in yet (tentative or confirmed).
     *
     * @return list<ReservationSummary>
     */
    public function arrivals(int $propertyId, string $date): array;

    /**
     * In-house bookings due to leave on or before the date.
     *
     * @return list<ReservationSummary>
     */
    public function departures(int $propertyId, string $date): array;

    /**
     * Every checked-in booking.
     *
     * @return list<ReservationSummary>
     */
    public function inHouse(int $propertyId): array;

    /**
     * Tentative bookings still waiting for their deposit, the soonest due first.
     *
     * @return list<ReservationSummary>
     */
    public function pendingDeposits(int $propertyId): array;

    /**
     * Rooms held by bookings for the night of the date (booked or in house).
     */
    public function roomsBooked(int $propertyId, string $date): int;
}
