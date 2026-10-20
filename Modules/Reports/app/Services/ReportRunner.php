<?php

namespace Modules\Reports\Services;

use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\DTOs\ReportTable;

/**
 * Runs a registered report for a filter (Step 7.1).
 */
class ReportRunner
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function run(string $key, ReportFilter $filter): ReportTable
    {
        return $this->registry->builder($key)->build($filter);
    }
}
