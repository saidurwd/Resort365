<?php

namespace Modules\IAM\Actions\Fortify;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantRule;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Modules\IAM\Models\User;

/**
 * Fortify: PUT /user/profile-information. A new email must be verified again.
 */
class UpdateUserProfileInformation extends Action implements UpdatesUserProfileInformation
{
    /**
     * @param  User  $user
     * @param  array<string, mixed>  $input
     */
    public function update($user, array $input): void
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', TenantRule::unique('users', 'email')->ignore($user->id)],
        ])->validateWithBag('updateProfileInformation');

        if ($data['email'] !== $user->email) {
            $user->forceFill(['name' => $data['name'], 'email' => $data['email'], 'email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();

            return;
        }

        $user->forceFill(['name' => $data['name']])->save();
    }
}
