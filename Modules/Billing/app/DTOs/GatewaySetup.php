<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayProvider;

/**
 * A property's gateway as an adapter needs it (Step 8.3): which one, sandbox or live, and the credentials.
 */
final readonly class GatewaySetup extends Data
{
    /**
     * @param  array<string, string>  $credentials
     */
    public function __construct(
        public int $gatewayId,
        public GatewayProvider $provider,
        public GatewayMode $mode,
        public array $credentials,
    ) {}

    public function credential(string $field): string
    {
        return $this->credentials[$field] ?? '';
    }
}
