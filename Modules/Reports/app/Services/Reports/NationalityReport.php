<?php

namespace Modules\Reports\Services\Reports;

use Modules\Core\Contracts\ReferenceData;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reports\Contracts\ReportBuilder;
use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reports\Services\Calculation\GuestMixBuilder;
use Modules\Reservation\Contracts\ReservationAnalytics;
use Modules\Reservation\DTOs\BookingFact;

class NationalityReport implements ReportBuilder
{
    public function __construct(
        private readonly ReservationAnalytics $analytics,
        private readonly GuestLookup $guests,
        private readonly ReferenceData $reference,
        private readonly GuestMixBuilder $builder,
    ) {}

    public function build(ReportFilter $filter): ReportTable
    {
        $bookings = $this->analytics->bookings($filter->propertyIds, 'arrival', $filter->from, $filter->to);
        $ids = array_values(array_unique(array_map(fn (BookingFact $booking): int => $booking->guestId, $bookings)));

        return $this->builder->nationalities($bookings, $this->guests->nationalities($ids), $this->reference->countries(), __('Nationality mix :from to :to', ['from' => $filter->from, 'to' => $filter->to]));
    }
}
