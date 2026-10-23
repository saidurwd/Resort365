<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Turns off a platform admin's two-factor sign-in when they have lost their phone and their recovery codes (from the command line
 * only). In production they must set it up again at their next sign-in.
 */
class ResetPlatformTwoFactor extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(PlatformAdmin $admin): PlatformAdmin
    {
        $admin->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->audit->log(null, 'admin.two_factor_reset', __('Two-factor sign-in of :name reset from the command line', ['name' => $admin->name]));

        return $admin;
    }
}
