<?php

namespace Modules\HR\DTOs;

use App\Support\DTOs\Data;
use Modules\HR\Enums\EmployeeStatus;

/**
 * An employee as other modules see them (Payroll, Housekeeping…); no HR model leaves the module.
 */
final readonly class EmployeeSummary extends Data
{
    public function __construct(
        public int $id,
        public string $employeeNo,
        public string $name,
        public int $propertyId,
        public int $departmentId,
        public string $designation,
        public EmployeeStatus $status,
        public ?int $managerId = null,
        public ?int $userId = null,
    ) {}
}
