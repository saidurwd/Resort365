<?php

namespace Modules\Restaurant\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A cashier's POS session was closed with its cash counted. Accounting posts a cash shortage or overage.
 */
class PosSessionClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $outletId,
        public readonly int $sessionId,
        public readonly string $businessDate,
        public readonly string $variance,
    ) {}
}
