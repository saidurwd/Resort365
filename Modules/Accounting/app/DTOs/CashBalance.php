<?php

namespace Modules\Accounting\DTOs;

use App\Support\DTOs\Data;

/**
 * The balance of one cash or bank account on a date, as the books hold it (Step 7.1). `kind` is "cash" or "bank".
 */
final readonly class CashBalance extends Data
{
    public function __construct(
        public string $name,
        public string $kind,
        public string $balance,
    ) {}
}
