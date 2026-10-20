<?php

namespace Modules\Payroll\Contracts;

use Modules\Payroll\Enums\PoolKind;

/**
 * What was collected for staff at a property in a period (Step 6.6): the service charge or tips held as a liability. Accounting
 * implements it from the ledger; without Accounting nothing is collected.
 */
interface PoolCollections
{
    /**
     * The amount collected (a decimal string) between two dates, both included.
     */
    public function collected(int $propertyId, string $from, string $to, PoolKind $kind): string;
}
