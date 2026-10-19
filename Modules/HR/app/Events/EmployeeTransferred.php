<?php

namespace Modules\HR\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An employee moved to another property (Step 6.1). Ids only.
 */
final readonly class EmployeeTransferred implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $tenantId,
        public int $employeeId,
        public int $fromPropertyId,
        public int $toPropertyId,
        public string $effectiveOn,
    ) {}
}
