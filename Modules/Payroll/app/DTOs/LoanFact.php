<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * A loan or advance paid out, or an installment written off, as Accounting books it (Step 6.5).
 */
final readonly class LoanFact extends Data
{
    public function __construct(
        public int $id,
        public string $loanNo,
        public int $propertyId,
        public string $date,
        public string $amount,
        public string $method,
        public int $employeeId,
    ) {}
}
