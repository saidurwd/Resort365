<?php

namespace Modules\FrontOffice\Contracts;

use Modules\FrontOffice\DTOs\DailyStatFact;
use Modules\FrontOffice\DTOs\StatTotals;

/**
 * The night audit's daily statistics, for the dashboard and reports (ARCHITECTURE §5.17, Step 7.1). Only properties the user may
 * see are answered. Dates are business dates, "Y-m-d", both ends included.
 */
interface StatisticsLookup
{
    /**
     * Each property's days, oldest first.
     *
     * @param  list<int>  $propertyIds
     * @return list<DailyStatFact>
     */
    public function between(array $propertyIds, string $from, string $to): array;

    /**
     * The days added up.
     *
     * @param  list<int>  $propertyIds
     */
    public function totals(array $propertyIds, string $from, string $to): StatTotals;

    /**
     * The most recent business date with statistics, or null.
     *
     * @param  list<int>  $propertyIds
     */
    public function latestDate(array $propertyIds): ?string;
}
