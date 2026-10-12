<?php

namespace Modules\Procurement\DTOs;

use App\Support\DTOs\Data;

final readonly class VendorSummary extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
    ) {}
}
