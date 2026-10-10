<?php

namespace Modules\Property\Services;

use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\DTOs\DepartmentSummary;
use Modules\Property\Models\Department;

class DepartmentDirectoryService implements DepartmentDirectory
{
    public function all(bool $activeOnly = true): array
    {
        return Department::query()->when($activeOnly, fn ($query) => $query->where('is_active', true))->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Department $department): DepartmentSummary => $this->summary($department))->values()->all();
    }

    public function find(int $id): ?DepartmentSummary
    {
        $department = Department::query()->find($id);

        return $department instanceof Department ? $this->summary($department) : null;
    }

    private function summary(Department $department): DepartmentSummary
    {
        return new DepartmentSummary($department->id, $department->code, $department->name, $department->is_active);
    }
}
