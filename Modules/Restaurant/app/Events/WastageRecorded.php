<?php

namespace Modules\Restaurant\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Food or ingredients were written off. Accounting books the wastage. (Step 5.7)
 */
class WastageRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $propertyId,
        public readonly int $wastageId,
    ) {}
}
