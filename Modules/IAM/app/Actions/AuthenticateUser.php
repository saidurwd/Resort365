<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Checks sign-in credentials for the current tenant (the tenant scope limits the lookup).
 * Returns null for wrong credentials; refuses deactivated accounts with a clear message.
 */
class AuthenticateUser extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(string $email, string $password): ?User
    {
        $user = User::query()->where('email', strtolower(trim($email)))->first();

        // Invited users have no password yet, so they fail here like any wrong password.
        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            return null;
        }

        if ($user->status === UserStatus::Inactive) {
            throw ValidationException::withMessages(['email' => __('This account has been deactivated. Contact your administrator.')]);
        }

        return $user->status === UserStatus::Active ? $user : null;
    }
}
