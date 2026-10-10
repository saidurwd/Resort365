<?php

namespace Modules\Billing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A folio charge was voided. If its revenue was already posted to the ledger, Accounting reverses it.
 */
class FolioChargeVoided implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $folioLineId,
        public readonly int $propertyId,
    ) {}
}
