<?php

namespace Modules\HR\Contracts;

use Modules\HR\DTOs\EmployeeSummary;

/**
 * The tenant's employees for other modules (Payroll, Housekeeping, Accounting dimensions). Lookups respect the
 * signed-in user's property access.
 */
interface EmployeeDirectory
{
    public function find(int $id): ?EmployeeSummary;

    /**
     * The employee linked to a user account, if any.
     */
    public function forUser(int $userId): ?EmployeeSummary;

    /**
     * Employees by name, matching the employee number or any part of the name.
     *
     * @return list<EmployeeSummary>
     */
    public function search(string $term, int $limit = 15, bool $activeOnly = true): array;

    /**
     * Employees of a property (and department), by name.
     *
     * @return list<EmployeeSummary>
     */
    public function forProperty(int $propertyId, ?int $departmentId = null, bool $activeOnly = true): array;
}
