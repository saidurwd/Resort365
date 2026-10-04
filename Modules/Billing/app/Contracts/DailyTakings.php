<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\DTOs\DailyTakingsSummary;

/**
 * A property's takings for a business date (ARCHITECTURE §5.7 night audit, flash report).
 */
interface DailyTakings
{
    public function forDate(int $propertyId, string $date): DailyTakingsSummary;
}
