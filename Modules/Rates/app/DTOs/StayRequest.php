<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * What a promotion is matched against: one room or cottage type in one rate plan.
 * nightly: stay date (Y-m-d) => price of the night (decimal string), in stay order.
 */
final readonly class StayRequest extends Data
{
    /**
     * @param  array<string, string>  $nightly
     */
    public function __construct(
        public int $ratePlanId,
        public string $unitKey,
        public array $nightly,
        public string $bookedOn,
        public ?string $promoCode = null,
    ) {}
}
