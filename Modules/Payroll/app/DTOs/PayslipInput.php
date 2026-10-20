<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * Everything the payslip calculation needs for one employee's month (Step 6.5).
 */
final readonly class PayslipInput extends Data
{
    /**
     * @param  list<StructureLine>  $lines  the salary structure
     * @param  array<string, string>  $employeeAmounts  amounts set for this employee, by component code
     * @param  list<string>  $includeAnnual  annual components paid in this run (one payment each)
     * @param  string  $fraction  share of the month worked, 1 for a whole month
     * @param  string  $serviceShare  the employee's share of the month's service charge pool (Step 6.6)
     * @param  string  $tipsShare  the employee's share of the month's tips pool (Step 6.6)
     */
    public function __construct(
        public array $lines,
        public string $basic,
        public ?TaxTableData $tax,
        public string $taxCategory,
        public string $taxArea,
        public array $employeeAmounts,
        public array $includeAnnual,
        public string $fraction,
        public string $absentDays,
        public string $unpaidLeaveDays,
        public int $overtimeMinutes,
        public string $loanDue,
        public string $advanceDue,
        public PayrollRules $rules,
        public string $serviceShare = '0',
        public string $tipsShare = '0',
    ) {}
}
