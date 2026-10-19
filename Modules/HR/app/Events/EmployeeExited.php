<?php

namespace Modules\HR\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * An employee left (Step 6.1). Ids only. Payroll settles them and IAM may deactivate their user later (TODO(step-6.4)).
 */
final readonly class EmployeeExited implements ShouldDispatchAfterCommit
{
    public function __construct(
        public int $tenantId,
        public int $employeeId,
        public int $propertyId,
        public string $exitedOn,
    ) {}
}
