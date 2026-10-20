<?php

namespace Modules\Payroll\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A loan or salary advance was paid to an employee (Step 6.5). Accounting books the receivable. Ids only.
 */
final readonly class LoanDisbursed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $tenantId,
        public int $loanId,
        public int $propertyId,
    ) {}
}
