<?php

namespace Modules\IAM\Actions\Fortify;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Modules\IAM\Models\User;

/**
 * Fortify: POST /reset-password (after following an emailed reset link).
 */
class ResetUserPassword extends Action implements ResetsUserPasswords
{
    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function reset($user, array $input): void
    {
        $data = Validator::make($input, [
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ])->validate();

        $user->forceFill(['password' => $data['password']])->save();
    }
}
