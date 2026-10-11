<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;
use Modules\Inventory\Enums\StoreKind;

final readonly class StoreSummary extends Data
{
    public function __construct(
        public int $id,
        public int $propertyId,
        public string $code,
        public string $name,
        public StoreKind $kind,
        public ?int $departmentId,
        public bool $isActive,
    ) {}
}
