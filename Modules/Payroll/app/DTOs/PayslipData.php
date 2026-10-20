<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * An employee's month worked out for a payroll run (Step 6.5). `gross` is the earnings paid this month, `deductions` those
 * other than income tax, `employerCost` what the employer adds on top.
 */
final readonly class PayslipData extends Data
{
    /**
     * @param  list<PayslipLineData>  $lines
     */
    public function __construct(
        public array $lines,
        public string $gross,
        public string $deductions,
        public string $tax,
        public string $net,
        public string $employerCost,
    ) {}
}
