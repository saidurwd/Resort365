<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Gives a platform admin a new authenticator secret and recovery codes, not yet in force: ConfirmPlatformTwoFactor turns it on once
 * the admin has typed a code from the app. Starting again replaces an unconfirmed secret.
 */
class StartPlatformTwoFactor extends Action
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $provider) {}

    public function handle(PlatformAdmin $admin): PlatformAdmin
    {
        $admin->forceFill([
            'two_factor_secret' => encrypt($this->provider->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt((string) json_encode(array_map(RecoveryCode::generate(...), range(1, 8)))),
            'two_factor_confirmed_at' => null,
        ])->save();

        return $admin;
    }
}
