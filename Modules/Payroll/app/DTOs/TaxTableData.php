<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * An income tax table as the calculator needs it (Step 6.4).
 */
final readonly class TaxTableData extends Data
{
    /**
     * @param  array<string, string>  $thresholds  taxpayer category => tax-free amount a year
     * @param  array<string, string>  $minimumTax  tax area => minimum tax a year once tax is due
     * @param  list<array{width: string|null, rate: string}>  $slabs  bands above the tax-free amount, in order; the last has no width
     */
    public function __construct(
        public array $thresholds,
        public array $minimumTax,
        public string $exemptionFraction,
        public string $exemptionCap,
        public array $slabs,
    ) {}
}
