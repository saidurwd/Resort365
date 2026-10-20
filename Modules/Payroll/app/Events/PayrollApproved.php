<?php

namespace Modules\Payroll\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A payroll run was approved and locked (Step 6.5). Accounting accrues the salaries. Ids only.
 */
final readonly class PayrollApproved implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $tenantId,
        public int $runId,
        public int $propertyId,
    ) {}
}
