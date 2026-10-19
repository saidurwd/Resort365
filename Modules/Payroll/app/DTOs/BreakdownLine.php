<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;

/**
 * One component's amount in a salary breakdown (Step 6.4); for an annual component the amount is one payment.
 */
final readonly class BreakdownLine extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public ComponentKind $kind,
        public bool $taxable,
        public ComponentFrequency $frequency,
        public int $annualTimes,
        public string $amount,
    ) {}
}
