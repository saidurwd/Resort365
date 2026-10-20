<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * The tenant's payroll settings the payslip calculation uses (Step 6.5).
 */
final readonly class PayrollRules extends Data
{
    public function __construct(
        public string $overtimeMultiplier = '2',
        public string $overtimeHoursPerMonth = '208',
        public int $salaryDaysBasis = 30,
    ) {}
}
