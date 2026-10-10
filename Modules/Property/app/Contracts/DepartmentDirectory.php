<?php

namespace Modules\Property\Contracts;

use Modules\Property\DTOs\DepartmentSummary;

/**
 * The tenant's departments (cost centres) for other modules: accounting dimensions, later payroll and
 * procurement.
 */
interface DepartmentDirectory
{
    /**
     * Departments by sort order and name.
     *
     * @return list<DepartmentSummary>
     */
    public function all(bool $activeOnly = true): array;

    public function find(int $id): ?DepartmentSummary;
}
