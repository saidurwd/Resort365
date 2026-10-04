<?php

namespace Modules\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Billing\Enums\RefundKind;

/**
 * Money was paid back (ARCHITECTURE §4.5). Reservation updates what the booking has paid
 * (reservationPaidTotal, null when the booking's total is unchanged, e.g. a security deposit);
 * Accounting will post the refund (Phase 4).
 */
class RefundIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $paymentId,
        public readonly int $propertyId,
        public readonly string $amount,
        public readonly RefundKind $kind,
        public readonly ?int $reservationId = null,
        public readonly ?string $reservationPaidTotal = null,
    ) {}
}
