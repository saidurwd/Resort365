<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\Enums\DiscountType;

/**
 * A promotion the property shows on its public website (Step 8.2a): what it gives, for which stays, and its code when it needs one.
 */
final readonly class WebsiteOffer extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?string $code,
        public DiscountType $discountType,
        public string $discountValue,
        public ?string $stayFrom,
        public ?string $stayTo,
        public ?string $bookTo,
        public ?int $minNights,
        public ?int $minAdvanceDays,
    ) {}
}
