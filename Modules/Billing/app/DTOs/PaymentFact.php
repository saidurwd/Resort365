<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;

/**
 * A payment or refund as the ledger needs it (Accounting, Step 4.2). Amounts are decimal strings.
 */
final readonly class PaymentFact extends Data
{
    public function __construct(
        public int $paymentId,
        public int $propertyId,
        public string $receiptNo,
        public PaymentType $type,
        public PaymentMethod $method,
        public string $amount,
        public string $businessDate,
        public ?int $reservationId,
        public ?int $folioId,
        public ?RefundKind $refundKind,
    ) {}
}
