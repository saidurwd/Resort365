<?php

namespace Modules\Accounting\DTOs;

use App\Support\DTOs\Data;

/**
 * Money moved from one cash or bank account to another.
 */
final readonly class TransferData extends Data
{
    public function __construct(
        public int $propertyId,
        public string $date,
        public int $fromBankAccountId,
        public int $toBankAccountId,
        public string $amount,
        public ?string $reference = null,
        public ?string $notes = null,
    ) {}
}
