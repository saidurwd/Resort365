<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\Enums\MealPlan;

/**
 * A rate plan as other modules see it (RateLookup). Amounts are decimal strings; dates Y-m-d.
 *
 * @param  list<string>  $channels
 */
final readonly class RatePlanSummary extends Data
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public int $id,
        public int $propertyId,
        public string $code,
        public string $name,
        public MealPlan $mealPlan,
        public string $mealAdultAmount,
        public string $mealChildAmount,
        public bool $isRefundable,
        public bool $pricesIncludeTax,
        public ?int $taxCategoryId,
        public ?string $validFrom,
        public ?string $validTo,
        public array $channels,
        public bool $isActive,
    ) {}

    public function isValidOn(string $date): bool
    {
        return ($this->validFrom === null || $date >= $this->validFrom) && ($this->validTo === null || $date <= $this->validTo);
    }

    public function sellsThrough(string $channel): bool
    {
        return in_array($channel, $this->channels, true);
    }
}
