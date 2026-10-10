<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * A cancelled booking as the ledger needs it (Accounting, Step 4.2): the fee kept and the day it was cancelled.
 */
final readonly class CancellationFact extends Data
{
    public function __construct(
        public int $reservationId,
        public int $propertyId,
        public string $code,
        public string $fee,
        public string $date,
    ) {}
}
