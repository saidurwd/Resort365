<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * A stored rate as the resolver sees it (one plan, one room or cottage type).
 */
final readonly class RateRow extends Data
{
    public function __construct(
        public int $id,
        public ?int $seasonId,
        public int $dowMask,
        public string $amount,
        public string $extraAdultAmount,
        public string $extraChildAmount,
    ) {}
}
