<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * The result of cancelling: fee kept, refund of what was paid, and what is still owed
 * (decimal strings). rule is the tier that applied (null = no charge / no-show charge).
 */
final readonly class CancellationQuote extends Data
{
    public function __construct(
        public string $fee,
        public string $refund,
        public string $owed,
        public ?CancellationRuleData $rule,
        public bool $noShow,
    ) {}
}
