<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\LineSource;

/**
 * One line of a worked-out payslip (Step 6.5).
 */
final readonly class PayslipLineData extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public ComponentKind $kind,
        public bool $taxable,
        public string $amount,
        public LineSource $source,
        public int $sortOrder,
    ) {}
}
