<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\PlanLimits;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Re-enables sign-in. A user who never accepted the invitation goes back to "invited". A user who comes back must fit the plan (Step 7.3).
 */
class ActivateUser extends Action
{
    public function __construct(private readonly PlanLimits $limits) {}

    public function handle(User $user): User
    {
        if ($user->status === UserStatus::Inactive) {
            $this->limits->ensureWithin('max_users', User::query()->whereIn('status', [UserStatus::Invited->value, UserStatus::Active->value])->count() + 1);
        }

        $user->forceFill([
            'status' => $user->password === null ? UserStatus::Invited : UserStatus::Active,
            'deactivated_at' => null,
        ])->save();

        return $user;
    }
}
