<?php

namespace Modules\HR\DTOs;

use App\Support\DTOs\Data;

/**
 * An employee's bank details (decrypted), for the salary transfer file (Step 6.5). Handle with care.
 */
final readonly class BankDetails extends Data
{
    public function __construct(
        public ?string $bank,
        public ?string $branch,
        public ?string $accountName,
        public ?string $accountNumber,
    ) {}
}
