<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Turns on a platform admin's two-factor sign-in once they type a valid code from their authenticator app.
 */
class ConfirmPlatformTwoFactor extends Action
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly PlatformAudit $audit,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(PlatformAdmin $admin, string $code): PlatformAdmin
    {
        if ($admin->two_factor_secret === null || ! $this->provider->verify(decrypt($admin->two_factor_secret), trim($code))) {
            throw ValidationException::withMessages(['code' => __('That code is not right. Type the 6 digits the app shows now.')]);
        }

        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->audit->log($admin, 'admin.two_factor_enabled', __(':name turned on two-factor sign-in', ['name' => $admin->name]));

        return $admin;
    }
}
