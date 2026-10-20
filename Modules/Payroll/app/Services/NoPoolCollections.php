<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\Contracts\PoolCollections;
use Modules\Payroll\Enums\PoolKind;

/**
 * The default `PoolCollections` for a tenant without Accounting: nothing is collected.
 */
class NoPoolCollections implements PoolCollections
{
    public function collected(int $propertyId, string $from, string $to, PoolKind $kind): string
    {
        return '0.00';
    }
}
