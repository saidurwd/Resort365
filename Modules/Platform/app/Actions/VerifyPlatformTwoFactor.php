<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Checks the second step of a platform admin's sign-in: a code from the authenticator app, or one of the recovery codes, which then
 * cannot be used again (it is replaced by a new one).
 */
class VerifyPlatformTwoFactor extends Action
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $provider) {}

    public function handle(PlatformAdmin $admin, ?string $code, ?string $recoveryCode): bool
    {
        if (! $admin->hasEnabledTwoFactorAuthentication() || $admin->two_factor_secret === null) {
            return false;
        }

        if ($code !== null && trim($code) !== '') {
            return $this->provider->verify(decrypt($admin->two_factor_secret), trim($code));
        }

        $recoveryCode = trim((string) $recoveryCode);

        if ($recoveryCode !== '' && in_array($recoveryCode, $admin->recoveryCodes(), true)) {
            $admin->replaceRecoveryCode($recoveryCode);

            return true;
        }

        return false;
    }
}
