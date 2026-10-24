<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A notification from a gateway (IPN, webhook) whose signature checked out (Step 8.3): the gateway's event id (to recognise a replay),
 * our reference, and what the confirmation needs (e.g. SSLCommerz's `val_id`).
 *
 * @phpstan-type Input array<string, mixed>
 */
final readonly class GatewayNotice extends Data
{
    /**
     * @param  array<string, mixed>  $input  what to pass to GuestPaymentGateway::confirm()
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $eventId,
        public ?string $reference,
        public array $input = [],
        public array $raw = [],
    ) {}
}
