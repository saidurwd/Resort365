<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * Where a posted charge landed: the line, its folio and the amount with tax (decimal string).
 */
final readonly class FolioPosting extends Data
{
    public function __construct(
        public int $lineId,
        public int $folioId,
        public string $folioNo,
        public string $total,
        public string $folioBalance,
    ) {}
}
