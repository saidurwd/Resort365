<?php

namespace App\Support\Authorization;

use InvalidArgumentException;

/**
 * Every permission in the system, registered by modules in their service providers:
 *
 *     app(PermissionRegistry::class)->register(__('Users & access'), [
 *         new PermissionDefinition('iam.user.view', __('View users'), [DefaultRole::GeneralManager]),
 *     ]);
 *
 * `php artisan permissions:sync` stores them and keeps every tenant's default roles up to date.
 */
class PermissionRegistry
{
    /**
     * @var array<string, PermissionDefinition>
     */
    private array $permissions = [];

    /**
     * @var array<string, string> module alias => group label
     */
    private array $groups = [];

    /**
     * @param  list<PermissionDefinition>  $permissions
     */
    public function register(string $groupLabel, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (isset($this->permissions[$permission->name])) {
                throw new InvalidArgumentException("Permission [{$permission->name}] is registered twice.");
            }

            $this->permissions[$permission->name] = $permission;
            $this->groups[$permission->module()] ??= $groupLabel;
        }
    }

    /**
     * @return array<string, PermissionDefinition>
     */
    public function all(): array
    {
        ksort($this->permissions);

        return $this->permissions;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    public function has(string $name): bool
    {
        return isset($this->permissions[$name]);
    }

    /**
     * Permissions grouped for display (e.g. the role editor): module alias => [label, permissions].
     *
     * @return array<string, array{label: string, permissions: list<PermissionDefinition>}>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $permission) {
            $grouped[$permission->module()] ??= ['label' => $this->groups[$permission->module()], 'permissions' => []];
            $grouped[$permission->module()]['permissions'][] = $permission;
        }

        return $grouped;
    }

    /**
     * Permission names a default role gets: all for Tenant Owner, every `*.view` for Auditor,
     * plus those that list the role explicitly.
     *
     * @return list<string>
     */
    public function namesFor(DefaultRole $role): array
    {
        $names = [];

        foreach ($this->all() as $permission) {
            if ($role === DefaultRole::TenantOwner
                || ($role === DefaultRole::Auditor && $permission->isView())
                || in_array($role, $permission->defaultRoles, true)) {
                $names[] = $permission->name;
            }
        }

        return $names;
    }
}
