<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\PaymentMethod;

/**
 * A payment to record for a reservation (RecordPayment). amount is a decimal string in the
 * reservation's currency. securityDeposit: a refundable deposit held during the stay. folioId: the
 * folio it settles (in house; null = the guest folio).
 */
final readonly class NewPayment extends Data
{
    public function __construct(
        public int $reservationId,
        public PaymentMethod $method,
        public string $amount,
        public ?string $reference = null,
        public ?string $notes = null,
        public ?int $receivedBy = null,
        public bool $securityDeposit = false,
        public ?int $folioId = null,
    ) {}
}
