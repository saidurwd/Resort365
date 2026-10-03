<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Modules\Core\Enums\TaxType;

/**
 * A calculated tax: amount is a decimal string rounded to 2 places.
 */
final readonly class TaxLine extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public TaxType $type,
        public string $rate,
        public string $amount,
    ) {}
}
