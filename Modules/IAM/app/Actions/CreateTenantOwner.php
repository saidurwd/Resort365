<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use App\Support\Authorization\DefaultRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Creates the Tenant Owner of a company that signed up by itself (Step 7.4): active, with the password they chose, holding the Tenant
 * Owner role. Only for a company with no users yet. They confirm their email address through the link sent to it.
 */
class CreateTenantOwner extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(string $name, string $email, string $password): User
    {
        Validator::make(['name' => $name, 'email' => $email, 'password' => $password], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ])->validate();

        if (User::query()->exists()) {
            throw ValidationException::withMessages(['email' => __('This company already has users.')]);
        }

        $user = $this->transaction(function () use ($name, $email, $password): User {
            $user = User::query()->create(['name' => trim($name), 'email' => strtolower(trim($email)), 'password' => $password, 'status' => UserStatus::Active]);
            $user->syncRoles([DefaultRole::TenantOwner->value]);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return $user;
    }
}
