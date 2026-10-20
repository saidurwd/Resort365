<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\DTOs\BookingFact;
use Modules\Reservation\DTOs\ForecastDay;

/**
 * What the reports read from bookings (ARCHITECTURE §5.17, Step 7.1). Facts only; only properties the user may see are answered.
 */
interface ReservationAnalytics
{
    /**
     * Bookings made (`$by` = "booked") or arriving (`$by` = "arrival") between two dates, both included.
     *
     * @param  list<int>  $propertyIds
     * @return list<BookingFact>
     */
    public function bookings(array $propertyIds, string $by, string $from, string $to): array;

    /**
     * Each night from the first date to the last on the books: rooms, rooms blocked, rooms booked or held, arrivals.
     *
     * @param  list<int>  $propertyIds
     * @return list<ForecastDay>
     */
    public function forecast(array $propertyIds, string $from, string $to): array;

    /**
     * How many stays (checked in or out) each guest has had, for the guests asked about.
     *
     * @param  list<int>  $guestIds
     * @return array<int, int>
     */
    public function stayCounts(array $guestIds): array;
}
