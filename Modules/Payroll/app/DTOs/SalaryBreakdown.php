<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * A month of an employee's pay worked out from the structure (Step 6.4). Amounts are decimal strings in the tenant's
 * currency. `gross` is the monthly earnings; annual components (festival bonuses) are not in it but are in `annualIncome`,
 * the yearly taxable salary the tax is worked out on.
 */
final readonly class SalaryBreakdown extends Data
{
    /**
     * @param  list<BreakdownLine>  $lines
     */
    public function __construct(
        public array $lines,
        public string $gross,
        public string $deductions,
        public string $tax,
        public string $net,
        public string $employerCost,
        public string $annualIncome,
        public ?TaxResult $taxDetails,
    ) {}
}
