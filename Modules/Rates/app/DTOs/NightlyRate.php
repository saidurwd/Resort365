<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\Enums\RateSource;

/**
 * The price of one night for one room or cottage type in a rate plan (before extras, meals,
 * discounts and taxes). Amounts are decimal strings in the property's currency.
 */
final readonly class NightlyRate extends Data
{
    public function __construct(
        public string $date,
        public string $amount,
        public string $extraAdultAmount,
        public string $extraChildAmount,
        public RateSource $source,
        public ?int $seasonId = null,
        public ?string $seasonName = null,
        public ?string $seasonColor = null,
    ) {}
}
