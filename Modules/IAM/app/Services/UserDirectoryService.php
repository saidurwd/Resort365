<?php

namespace Modules\IAM\Services;

use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

class UserDirectoryService implements UserDirectory
{
    public function all(): array
    {
        return User::query()
            ->whereIn('status', [UserStatus::Active->value, UserStatus::Invited->value])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): UserSummary => new UserSummary($user->id, $user->name, $user->email, $user->status->value))
            ->values()
            ->all();
    }

    public function userCan(int $userId, string $permission): bool
    {
        return User::query()->find($userId)?->checkPermissionTo($permission) ?? false;
    }
}
