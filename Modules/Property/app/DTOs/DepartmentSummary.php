<?php

namespace Modules\Property\DTOs;

use App\Support\DTOs\Data;

/**
 * A department (cost centre) as other modules see it, e.g. for accounting dimensions.
 */
final readonly class DepartmentSummary extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public bool $isActive,
    ) {}
}
