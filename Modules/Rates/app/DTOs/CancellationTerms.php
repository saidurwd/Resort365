<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\Enums\CancellationChargeType;

/**
 * A cancellation policy as the CancellationFeeCalculator sees it.
 */
final readonly class CancellationTerms extends Data
{
    /**
     * @param  list<CancellationRuleData>  $rules
     */
    public function __construct(
        public array $rules,
        public ?CancellationChargeType $noShowChargeType = null,
        public ?string $noShowChargeValue = null,
    ) {}
}
