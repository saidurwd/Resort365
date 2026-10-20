<?php

namespace Modules\Reports\Services\Reports;

use Modules\Guest\Contracts\GuestLookup;
use Modules\Reports\Contracts\ReportBuilder;
use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reports\Services\Calculation\GuestMixBuilder;
use Modules\Reservation\Contracts\ReservationAnalytics;
use Modules\Reservation\DTOs\BookingFact;

class RepeatGuestsReport implements ReportBuilder
{
    public function __construct(
        private readonly ReservationAnalytics $analytics,
        private readonly GuestLookup $guests,
        private readonly GuestMixBuilder $builder,
    ) {}

    public function build(ReportFilter $filter): ReportTable
    {
        $bookings = $this->analytics->bookings($filter->propertyIds, 'arrival', $filter->from, $filter->to);
        $ids = array_values(array_unique(array_map(fn (BookingFact $booking): int => $booking->guestId, $bookings)));
        $lifetime = $this->analytics->stayCounts($ids);
        $repeat = array_keys(array_filter($lifetime, fn (int $stays): bool => $stays >= 2));

        return $this->builder->repeatGuests($bookings, $lifetime, $this->guests->names($repeat), $filter->limit, __('Repeat guests :from to :to', ['from' => $filter->from, 'to' => $filter->to]));
    }
}
