<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\GatewayOutcome;

/**
 * What a gateway confirmed when asked (Step 8.3): the outcome, the amount and currency it took, its transaction id (needed for
 * refunds), and the response without secrets for the log.
 */
final readonly class GatewayResult extends Data
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public GatewayOutcome $outcome,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $transactionId = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
