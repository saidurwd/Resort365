<?php

namespace Modules\Payroll\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A payroll run was paid (Step 6.5). Accounting moves the money out of the bank or cash. Ids only.
 */
final readonly class PayrollPaid implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $tenantId,
        public int $runId,
        public int $propertyId,
    ) {}
}
