<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;
use Modules\Inventory\Enums\UnitKind;

final readonly class UnitSummary extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public UnitKind $kind,
        public string $factor,
    ) {}
}
