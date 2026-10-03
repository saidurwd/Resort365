<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Modules\Core\Enums\TaxType;

/**
 * One tax as the calculator applies it: rate is a percentage (percent) or an amount per unit (fixed).
 */
final readonly class TaxRule extends Data
{
    public function __construct(
        public string $code,
        public string $name,
        public TaxType $type,
        public string $rate,
        public bool $compound = false,
    ) {}
}
