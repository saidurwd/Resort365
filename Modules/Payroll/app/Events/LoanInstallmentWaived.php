<?php

namespace Modules\Payroll\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A loan installment was written off (Step 6.5). Accounting writes the receivable off. Ids only.
 */
final readonly class LoanInstallmentWaived implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $tenantId,
        public int $installmentId,
        public int $propertyId,
    ) {}
}
