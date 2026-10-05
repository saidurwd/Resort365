<?php

namespace Modules\IAM\Services;

use Modules\IAM\Contracts\RoleDirectory;
use Modules\IAM\DTOs\RoleSummary;
use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;

class RoleDirectoryService implements RoleDirectory
{
    public function all(): array
    {
        return Role::query()->orderByDesc('is_system')->orderBy('name')->get()
            ->map(fn (Role $role): RoleSummary => new RoleSummary($role->id, $role->label(), $role->defaultRole()?->value))
            ->values()->all();
    }

    public function roleIdsOf(int $userId): array
    {
        $user = User::query()->find($userId);

        return $user instanceof User ? $user->roles()->pluck('roles.id')->map(fn ($id): int => (int) $id)->values()->all() : [];
    }
}
