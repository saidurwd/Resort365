<?php

namespace Modules\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment was received (ARCHITECTURE §4.5). Reservation updates the booking's payment status
 * and auto-confirms it when the deposit is covered; Accounting will post the receipt (Phase 4).
 * reservationPaidTotal is everything received for the reservation so far, so listeners can set
 * it rather than add to it. Amounts are decimal strings.
 */
class PaymentReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $paymentId,
        public readonly int $propertyId,
        public readonly string $receiptNo,
        public readonly string $amount,
        public readonly string $currencyCode,
        public readonly ?int $reservationId = null,
        public readonly ?string $reservationPaidTotal = null,
        public readonly ?int $receivedBy = null,
    ) {}
}
