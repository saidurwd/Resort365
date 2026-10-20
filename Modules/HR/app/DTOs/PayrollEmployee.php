<?php

namespace Modules\HR\DTOs;

use App\Support\DTOs\Data;

/**
 * An employee who worked at a property during a month, as Payroll needs them (Step 6.5).
 */
final readonly class PayrollEmployee extends Data
{
    public function __construct(
        public int $id,
        public string $employeeNo,
        public string $name,
        public string $designation,
        public int $departmentId,
        public string $joinedOn,
        public ?string $exitedOn,
        public ?string $email,
    ) {}
}
