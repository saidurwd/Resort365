<?php

namespace Modules\IAM\Policies;

use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;

/**
 * Role management (permissions `iam.role.*`). Default (system) roles can be viewed but
 * never changed or deleted.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('iam.role.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->checkPermissionTo('iam.role.view');
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo('iam.role.create');
    }

    public function update(User $actor, Role $role): bool
    {
        return ! $role->is_system && $actor->checkPermissionTo('iam.role.update');
    }

    public function delete(User $actor, Role $role): bool
    {
        return ! $role->is_system && $actor->checkPermissionTo('iam.role.delete');
    }
}
