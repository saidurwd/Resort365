<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\Enums\CancellationChargeType;

/**
 * One cancellation tier: from…to days before arrival (to null = or more).
 */
final readonly class CancellationRuleData extends Data
{
    public function __construct(
        public int $daysBeforeFrom,
        public ?int $daysBeforeTo,
        public CancellationChargeType $chargeType,
        public string $chargeValue,
    ) {}

    public function covers(int $daysBefore): bool
    {
        return $daysBefore >= $this->daysBeforeFrom && ($this->daysBeforeTo === null || $daysBefore <= $this->daysBeforeTo);
    }
}
