<?php

namespace Modules\Reports\Contracts;

use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\DTOs\ReportTable;

/**
 * One report: reads its facts through other modules' contracts and answers a table (Step 7.1).
 */
interface ReportBuilder
{
    public function build(ReportFilter $filter): ReportTable;
}
