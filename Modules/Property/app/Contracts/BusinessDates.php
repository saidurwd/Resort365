<?php

namespace Modules\Property\Contracts;

use Modules\Property\Exceptions\BusinessDateMismatch;

/**
 * The property's business date (ARCHITECTURE §5.7): moved on by one day by the night audit only.
 */
interface BusinessDates
{
    /**
     * Moves the business date from $from (Y-m-d) to the next day, only if it is still $from, so two
     * audits of the same date cannot both advance it. Call it inside the audit's transaction.
     *
     * @return string the new business date (Y-m-d)
     *
     * @throws BusinessDateMismatch when the business date is no longer $from
     */
    public function advance(int $propertyId, string $from): string;
}
