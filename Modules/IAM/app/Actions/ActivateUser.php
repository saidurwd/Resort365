<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Re-enables sign-in. A user who never accepted the invitation goes back to "invited".
 */
class ActivateUser extends Action
{
    public function handle(User $user): User
    {
        $user->forceFill([
            'status' => $user->password === null ? UserStatus::Invited : UserStatus::Active,
            'deactivated_at' => null,
        ])->save();

        return $user;
    }
}
