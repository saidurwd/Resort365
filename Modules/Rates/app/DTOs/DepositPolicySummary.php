<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * The deposit policy that applies to a rate plan (its own, or the property's default).
 */
final readonly class DepositPolicySummary extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public DepositTerms $terms,
    ) {}
}
