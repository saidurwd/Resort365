<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Sets the invited user's password and activates the account. The emailed link proves
 * the address, so the email counts as verified.
 */
class AcceptInvitation extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $name, string $password): User
    {
        if ($user->status !== UserStatus::Invited) {
            throw ValidationException::withMessages(['password' => __('This invitation has already been used.')]);
        }

        $user->forceFill([
            'name' => $name,
            'password' => $password,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
