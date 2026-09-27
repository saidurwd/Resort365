<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Blocks sign-in. Open sessions end on the user's next request (EnsureUserIsActive).
 */
class DeactivateUser extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, User $actor): User
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => __('You cannot deactivate your own account.')]);
        }

        if (AssignRoles::isLastOwner($user)) {
            throw ValidationException::withMessages(['user' => __('The last Tenant Owner cannot be deactivated.')]);
        }

        $user->forceFill(['status' => UserStatus::Inactive, 'deactivated_at' => now()])->save();

        return $user;
    }
}
