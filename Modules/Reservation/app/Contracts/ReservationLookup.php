<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\DTOs\MealEntitlement;
use Modules\Reservation\DTOs\NightOccupancy;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\DTOs\RoomOccupancy;

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

    /**
     * The night of $date (Y-m-d) as stayed at the property, for the night audit's statistics.
     */
    public function occupancy(int $propertyId, string $date): NightOccupancy;

    /**
     * The property's rooms with a booking on $date (Y-m-d): in house, leaving or arriving.
     *
     * @return array<int, RoomOccupancy> room id => occupancy
     */
    public function roomOccupancy(int $propertyId, string $date): array;

    /**
     * The meals the booking's checked-in rooms include on a date (their rate plans' meal plans), as
     * covers per meal period, for outlets redeeming meal plans.
     */
    public function mealEntitlement(int $reservationId, string $date): MealEntitlement;
}
