<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Models\Department;

/**
 * Deletes (soft) a department. TODO(step-4.1): refuse while journal lines (and later stores, employees) use it.
 */
class DeleteDepartment extends Action
{
    public function handle(Department $department): void
    {
        $department->delete();
    }
}
