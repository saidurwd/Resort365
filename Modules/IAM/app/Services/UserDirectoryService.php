<?php

namespace Modules\IAM\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
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

    public function notify(array $userIds, Notification $notification): void
    {
        $users = User::query()->whereIn('id', $userIds)->where('status', UserStatus::Active->value)->get();

        if ($users->isNotEmpty()) {
            NotificationFacade::send($users, $notification);
        }
    }
}
