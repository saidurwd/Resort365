<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * The income tax on a year's salary (Step 6.4); amounts are decimal strings.
 */
final readonly class TaxResult extends Data
{
    public function __construct(
        public string $income,
        public string $exemption,
        public string $taxableIncome,
        public string $threshold,
        public string $tax,
        public bool $minimumApplied,
        public string $monthly,
    ) {}
}
