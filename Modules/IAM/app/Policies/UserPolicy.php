<?php

namespace Modules\IAM\Policies;

use Modules\IAM\Models\User;

/**
 * User management (permissions `iam.user.*`). The tenant scope already limits every
 * lookup to the current tenant.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isActive() && $actor->checkPermissionTo('iam.user.view');
    }

    public function invite(User $actor): bool
    {
        return $actor->isActive() && $actor->checkPermissionTo('iam.user.invite');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isActive() && $actor->tenant_id === $user->tenant_id && $actor->checkPermissionTo('iam.user.update');
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $actor->is($user);
    }
}
