<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;

/**
 * A closed POS session's cash result as the ledger needs it (Accounting, Step 4.3): negative variance = short.
 */
final readonly class SessionFact extends Data
{
    public function __construct(
        public int $sessionId,
        public int $propertyId,
        public int $outletId,
        public string $outletName,
        public string $businessDate,
        public string $variance,
    ) {}
}
