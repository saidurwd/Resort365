<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use App\Support\Authorization\DefaultRole;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;

/**
 * Sets a user's roles (role ids of the current tenant). The tenant always keeps at least
 * one active Tenant Owner.
 */
class AssignRoles extends Action
{
    /**
     * @param  list<int>  $roleIds
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $roleIds): User
    {
        $roles = Role::query()->whereKey($roleIds)->get();
        $ownerRole = DefaultRole::TenantOwner->value;

        if ($user->hasRole($ownerRole) && ! $roles->contains('name', $ownerRole) && self::isLastOwner($user)) {
            throw ValidationException::withMessages(['roles' => __('The last Tenant Owner must keep that role.')]);
        }

        $before = $user->getRoleNames()->sort()->values()->all();
        $user->syncRoles($roles);
        $after = $roles->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            activity()->performedOn($user)->event('updated')
                ->withProperties(['old' => ['roles' => implode(', ', $before)], 'attributes' => ['roles' => implode(', ', $after)]])
                ->log('User roles changed');
        }

        return $user;
    }

    /**
     * Whether the user is the tenant's only active Tenant Owner.
     */
    public static function isLastOwner(User $user): bool
    {
        return $user->hasRole(DefaultRole::TenantOwner->value)
            && User::role(DefaultRole::TenantOwner->value)->where('status', 'active')->whereKeyNot($user->id)->doesntExist();
    }
}
