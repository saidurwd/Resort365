<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Enums\OnlinePaymentPurpose;

/**
 * An online payment another module asks for (Step 8.3; the website): the booking, the gateway, how much and why, and where the guest
 * returns afterwards.
 */
final readonly class OnlinePaymentRequest extends Data
{
    public function __construct(
        public int $reservationId,
        public GatewayProvider $provider,
        public string $amount,
        public OnlinePaymentPurpose $purpose,
        public string $returnUrl,
    ) {}
}
