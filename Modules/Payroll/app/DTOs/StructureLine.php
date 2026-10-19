<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;

/**
 * One line of a salary structure as the calculator needs it (Step 6.4).
 */
final readonly class StructureLine extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public ComponentKind $kind,
        public bool $taxable,
        public ComponentFrequency $frequency,
        public int $annualTimes,
        public CalcType $calcType,
        public ?string $amount = null,
        public ?string $percent = null,
        public ?string $percentOf = null,
        public ?string $formula = null,
    ) {}
}
