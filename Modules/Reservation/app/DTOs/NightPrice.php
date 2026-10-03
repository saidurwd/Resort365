<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * One night before discount and taxes: the base rate and the extra-guest charges (decimal strings).
 */
final readonly class NightPrice extends Data
{
    public function __construct(
        public string $date,
        public string $base,
        public string $extras,
        public string $source,
        public ?string $seasonName = null,
    ) {}
}
