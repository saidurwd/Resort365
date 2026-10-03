<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * The deposit a booking needs: amounts are decimal strings; dueAt is a timestamp (ISO 8601) and
 * balanceDueOn a date (Y-m-d). dueAt is null when nothing is due in advance.
 */
final readonly class DepositQuote extends Data
{
    public function __construct(
        public string $percent,
        public string $amount,
        public string $balance,
        public ?string $dueAt,
        public string $balanceDueOn,
        public bool $fullPaymentRequired,
        public bool $autoCancelUnpaid,
    ) {}
}
