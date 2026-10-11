<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;

final readonly class StockIssueSummary extends Data
{
    public function __construct(
        public int $id,
        public string $issueNo,
        public int $storeId,
        public ?int $departmentId,
        public string $totalCost,
        public int $lineCount,
    ) {}
}
