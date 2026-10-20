<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * An approved payroll run as Accounting books it (Step 6.5). The expense is the pay the run cost, by department (net pay + tax
 * + provident fund + loan recoveries, so deductions that cut the cost, like absence, are already out); the employer's
 * provident fund and other costs are on top. Amounts are decimal strings.
 */
final readonly class PayrollRunFact extends Data
{
    /**
     * @param  list<array{department_id: int|null, amount: string}>  $expenseByDepartment
     */
    public function __construct(
        public int $runId,
        public string $runNo,
        public int $propertyId,
        public string $date,
        public array $expenseByDepartment,
        public string $employerProvidentFund,
        public string $employerOther,
        public string $net,
        public string $tax,
        public string $employeeProvidentFund,
        public string $loanRecovery,
    ) {}
}
