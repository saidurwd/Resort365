<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\Enums\DiscountType;

/**
 * A promotion as the PromotionMatcher sees it. Empty conditions mean "any"; dates are Y-m-d.
 */
final readonly class PromotionTerms extends Data
{
    /**
     * @param  list<int>  $ratePlanIds
     * @param  list<string>  $unitKeys
     */
    public function __construct(
        public int $id,
        public ?string $code,
        public string $name,
        public DiscountType $discountType,
        public string $discountValue,
        public ?string $stayFrom = null,
        public ?string $stayTo = null,
        public ?string $bookFrom = null,
        public ?string $bookTo = null,
        public ?int $minNights = null,
        public ?int $maxNights = null,
        public ?int $minAdvanceDays = null,
        public array $ratePlanIds = [],
        public array $unitKeys = [],
        public ?int $usageLimit = null,
        public int $timesUsed = 0,
        public bool $isActive = true,
    ) {}
}
