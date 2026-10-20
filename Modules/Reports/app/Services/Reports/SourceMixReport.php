<?php

namespace Modules\Reports\Services\Reports;

use Modules\Reports\Contracts\ReportBuilder;
use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reports\Services\Calculation\SourceMixBuilder;
use Modules\Reservation\Contracts\ReservationAnalytics;

class SourceMixReport implements ReportBuilder
{
    public function __construct(
        private readonly ReservationAnalytics $analytics,
        private readonly SourceMixBuilder $builder,
    ) {}

    public function build(ReportFilter $filter): ReportTable
    {
        return $this->builder->build($this->analytics->bookings($filter->propertyIds, $filter->by, $filter->from, $filter->to), __('Booking sources :from to :to', ['from' => $filter->from, 'to' => $filter->to]));
    }
}
