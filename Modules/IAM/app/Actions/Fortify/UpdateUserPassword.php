<?php

namespace Modules\IAM\Actions\Fortify;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Modules\IAM\Models\User;

/**
 * Fortify: PUT /user/password.
 */
class UpdateUserPassword extends Action implements UpdatesUserPasswords
{
    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        $data = Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill(['password' => $data['password']])->save();
    }
}
