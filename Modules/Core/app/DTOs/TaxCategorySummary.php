<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * A tax category for pickers in other modules.
 */
final readonly class TaxCategorySummary extends Data
{
    /**
     * @param  list<string>  $taxes  tax names in calculation order
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public array $taxes,
        public bool $isActive,
    ) {}
}
