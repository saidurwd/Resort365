<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A payment to start at a gateway (Step 8.3): our reference (the online payment's uuid), the amount, who pays, and where the gateway
 * sends the guest back (`returnUrl`, with `?outcome=success|fail|cancel` added by adapters that need it) and its notifications.
 */
final readonly class GatewayCheckout extends Data
{
    public function __construct(
        public string $reference,
        public string $amount,
        public string $currency,
        public string $description,
        public string $customerName,
        public ?string $customerEmail,
        public ?string $customerPhone,
        public string $returnUrl,
        public string $notifyUrl,
    ) {}
}
