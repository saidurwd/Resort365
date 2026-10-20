<?php

namespace Modules\Reports\Services\Reports;

use Modules\Reports\Contracts\ReportBuilder;
use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reports\Services\Calculation\ForecastBuilder;
use Modules\Reservation\Contracts\ReservationAnalytics;

class ForecastReport implements ReportBuilder
{
    public function __construct(
        private readonly ReservationAnalytics $analytics,
        private readonly ForecastBuilder $builder,
    ) {}

    public function build(ReportFilter $filter): ReportTable
    {
        return $this->builder->build($this->analytics->forecast($filter->propertyIds, $filter->from, $filter->to), __('Occupancy forecast :from to :to', ['from' => $filter->from, 'to' => $filter->to]));
    }
}
