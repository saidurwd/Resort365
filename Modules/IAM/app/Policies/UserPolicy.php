<?php

namespace Modules\IAM\Policies;

use Modules\IAM\Models\User;

/**
 * User management. The tenant scope already limits every lookup to the current tenant.
 *
 * TODO(step-0.6): require the iam.user.view / iam.user.invite / iam.user.update permissions.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isActive();
    }

    public function invite(User $actor): bool
    {
        return $actor->isActive();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isActive() && $actor->tenant_id === $user->tenant_id;
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $actor->is($user);
    }
}
