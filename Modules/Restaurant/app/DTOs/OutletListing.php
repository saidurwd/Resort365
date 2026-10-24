<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;
use Modules\Restaurant\Enums\OutletType;

/**
 * An outlet as the public website's Dining page shows it (Step 8.2a): its name, kind and opening hours per weekday (`mon`…`sun`,
 * `H:i` or null when closed).
 */
final readonly class OutletListing extends Data
{
    /**
     * @param  array<string, array{open: string|null, close: string|null}>  $openingHours
     */
    public function __construct(
        public int $id,
        public string $name,
        public OutletType $type,
        public array $openingHours,
    ) {}
}
