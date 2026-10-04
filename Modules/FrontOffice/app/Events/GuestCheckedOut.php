<?php

namespace Modules\FrontOffice\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A booking was checked out, its folios settled and invoiced (ARCHITECTURE §4.5). Housekeeping marks
 * its rooms dirty and creates cleaning tasks (Step 2.7).
 */
class GuestCheckedOut implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $roomIds  the rooms the booking occupied
     * @param  list<int>  $invoiceIds
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $reservationId,
        public readonly array $roomIds,
        public readonly array $invoiceIds,
    ) {}
}
