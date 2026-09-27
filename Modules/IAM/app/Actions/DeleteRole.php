<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Models\Role;

/**
 * Deletes a custom role that no user holds.
 */
class DeleteRole extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('Default roles cannot be deleted.')]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('Remove this role from its users before deleting it.')]);
        }

        $role->delete();
    }
}
