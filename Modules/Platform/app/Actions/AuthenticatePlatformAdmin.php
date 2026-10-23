<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Signs a platform admin in with the `platform` guard. An admin with two-factor sign-in is only checked here and is not signed in;
 * signIn() completes it once the second step passes (PlatformTwoFactorController).
 */
class AuthenticatePlatformAdmin extends Action
{
    /**
     * @return PlatformAdmin the admin; signed in unless their two-factor sign-in still needs a code
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password, bool $remember, string $ip): PlatformAdmin
    {
        $credentials = ['email' => strtolower($email), 'password' => $password];

        if (! Auth::guard('platform')->validate($credentials)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $admin = PlatformAdmin::query()->where('email', $credentials['email'])->firstOrFail();

        return $admin->hasEnabledTwoFactorAuthentication() ? $admin : $this->signIn($admin, $remember, $ip);
    }

    public function signIn(PlatformAdmin $admin, bool $remember, string $ip): PlatformAdmin
    {
        Auth::guard('platform')->login($admin, $remember);
        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->save();

        return $admin;
    }
}
