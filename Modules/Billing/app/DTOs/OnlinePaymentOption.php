<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\GatewayProvider;

/**
 * A gateway a guest can pay a property through (Step 8.3).
 */
final readonly class OnlinePaymentOption extends Data
{
    public function __construct(
        public GatewayProvider $provider,
        public string $label,
        public bool $sandbox,
    ) {}
}
