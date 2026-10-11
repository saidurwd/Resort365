<?php

namespace Modules\IAM\Services;

use Illuminate\Notifications\Notification;
use Modules\Core\Contracts\ApprovalParticipants;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Who may decide, what they are called and how they are told, for Core's approval engine (Step 5.1).
 */
class ApprovalParticipantsService implements ApprovalParticipants
{
    public function __construct(private readonly UserDirectory $users) {}

    public function userCan(int $userId, string $permission): bool
    {
        return $this->users->userCan($userId, $permission);
    }

    public function holders(string $permission): array
    {
        return User::query()->where('status', UserStatus::Active->value)->get()
            ->filter(fn (User $user): bool => $user->checkPermissionTo($permission))
            ->map(fn (User $user): int => $user->id)->values()->all();
    }

    public function colleagues(): array
    {
        return User::query()->where('status', UserStatus::Active->value)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function names(array $userIds): array
    {
        return $userIds === [] ? [] : User::query()->whereIn('id', $userIds)->pluck('name', 'id')->all();
    }

    public function notify(array $userIds, Notification $notification): void
    {
        $this->users->notify($userIds, $notification);
    }
}
