<?php

namespace Modules\Guest\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Two guest profiles were merged: records of other modules that point at $mergedGuestId
 * (reservations, folios…) should be moved to $keptGuestId.
 */
class GuestsMerged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $keptGuestId,
        public readonly int $mergedGuestId,
    ) {}
}
