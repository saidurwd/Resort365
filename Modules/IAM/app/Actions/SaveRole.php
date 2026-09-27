<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Models\Role;

/**
 * Creates or updates a custom role and its permissions (input validated by SaveRoleRequest).
 */
class SaveRole extends Action
{
    /**
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    public function handle(?Role $role, string $name, ?string $description, array $permissions): Role
    {
        if ($role?->is_system) {
            throw ValidationException::withMessages(['name' => __('Default roles cannot be changed. Create a custom role instead.')]);
        }

        return $this->transaction(function () use ($role, $name, $description, $permissions): Role {
            $role ??= new Role(['guard_name' => 'web']);
            $role->fill(['name' => $name, 'description' => $description])->save();
            $role->syncPermissions($permissions);

            return $role;
        });
    }
}
