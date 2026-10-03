<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\DepositType;

/**
 * A deposit policy as the DepositCalculator sees it (percentages and amounts as decimal strings).
 */
final readonly class DepositTerms extends Data
{
    public function __construct(
        public DepositType $type,
        public ?string $minPercent,
        public string $defaultPercent,
        public ?string $maxPercent,
        public ?string $fixedAmount,
        public int $dueWithinMinutes,
        public bool $autoCancelUnpaid,
        public BalanceDueRule $balanceDueRule,
        public ?int $balanceDueDays = null,
        public ?int $fullPaymentWithinHours = null,
    ) {}
}
