<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A started gateway payment (Step 8.3): where to send the guest, and the gateway's own reference (session, payment id).
 */
final readonly class GatewayStart extends Data
{
    public function __construct(
        public string $redirectUrl,
        public string $gatewayRef,
    ) {}
}
