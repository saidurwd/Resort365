<?php

namespace Modules\Property\DTOs;

use App\Support\DTOs\Data;

/**
 * A property as other modules see it.
 */
final readonly class PropertySummary extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $timezone,
        public string $currencyCode,
        public string $checkInTime,
        public string $checkOutTime,
        public string $businessDate,
        public bool $isActive,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {}
}
