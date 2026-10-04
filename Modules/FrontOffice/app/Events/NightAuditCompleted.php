<?php

namespace Modules\FrontOffice\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A property's night audit finished: businessDate (Y-m-d) is closed and the property now works on
 * nextBusinessDate. Accounting posts the day's revenue from it (Phase 4).
 */
class NightAuditCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly string $businessDate,
        public readonly string $nextBusinessDate,
    ) {}
}
