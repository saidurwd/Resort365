<?php

namespace Modules\FrontOffice\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A booking was checked in (ARCHITECTURE §4.5). Housekeeping marks its rooms occupied (Step 2.7).
 */
class GuestCheckedIn implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $roomIds  every room the booking occupies
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly array $roomIds,
    ) {}
}
