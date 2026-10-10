<?php

namespace Modules\Accounting\DTOs;

use App\Support\DTOs\Data;
use Modules\Accounting\Enums\VoucherType;

/**
 * What a quick voucher records. `amount` is the cash received or paid; on an expense `taxAmount` of it is input VAT.
 */
final readonly class VoucherData extends Data
{
    public function __construct(
        public VoucherType $type,
        public int $propertyId,
        public string $date,
        public int $accountId,
        public int $cashAccountId,
        public string $amount,
        public string $description,
        public string $taxAmount = '0.00',
        public ?int $departmentId = null,
        public ?string $payee = null,
        public ?string $reference = null,
        public ?string $chequeNo = null,
        public ?string $chequeDate = null,
    ) {}
}
