<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\RefundKind;

/**
 * Money to pay back (RefundPayment). sourceId depends on the kind: the security deposit payment,
 * the credit note, or the folio with a credit balance; a cancellation refund needs none.
 */
final readonly class NewRefund extends Data
{
    public function __construct(
        public int $reservationId,
        public RefundKind $kind,
        public PaymentMethod $method,
        public string $amount,
        public string $reason,
        public ?int $sourceId = null,
        public ?string $reference = null,
        public ?int $issuedBy = null,
    ) {}
}
