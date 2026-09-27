<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Signs a platform admin in with the `platform` guard.
 */
class AuthenticatePlatformAdmin extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(string $email, string $password, bool $remember, string $ip): PlatformAdmin
    {
        $guard = Auth::guard('platform');

        if (! $guard->attempt(['email' => strtolower($email), 'password' => $password], $remember)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        /** @var PlatformAdmin $admin */
        $admin = $guard->user();
        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $ip])->save();

        return $admin;
    }
}
