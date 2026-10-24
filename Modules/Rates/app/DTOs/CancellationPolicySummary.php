<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * A cancellation policy with its terms, for showing it (e.g. the public website's Policies page, Step 8.2a).
 */
final readonly class CancellationPolicySummary extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public CancellationTerms $terms,
    ) {}
}
