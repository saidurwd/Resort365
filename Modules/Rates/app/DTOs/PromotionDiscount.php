<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * The discount a promotion gives a stay (decimal string), and how many nights it covered.
 */
final readonly class PromotionDiscount extends Data
{
    public function __construct(
        public int $promotionId,
        public ?string $code,
        public string $name,
        public string $amount,
        public int $nights,
    ) {}
}
