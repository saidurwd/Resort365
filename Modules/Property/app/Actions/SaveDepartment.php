<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Models\Department;

/**
 * Creates or updates a department (validated by SaveDepartmentRequest).
 */
class SaveDepartment extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Department $department, array $data): Department
    {
        $department ??= new Department;
        $department->fill($data)->save();

        return $department;
    }
}
