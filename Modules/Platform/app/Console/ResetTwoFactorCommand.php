<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Platform\Actions\ResetPlatformTwoFactor;
use Modules\Platform\Models\PlatformAdmin;

#[Description('Turn off a platform admin\'s two-factor sign-in (lost phone and recovery codes)')]
#[Signature('platform:reset-two-factor {email : The admin\'s email address}')]
class ResetTwoFactorCommand extends Command
{
    public function handle(ResetPlatformTwoFactor $reset): int
    {
        $admin = PlatformAdmin::query()->where('email', strtolower((string) $this->argument('email')))->first();

        if (! $admin instanceof PlatformAdmin) {
            $this->error(__('No platform admin has that email address.'));

            return self::FAILURE;
        }

        $reset->handle($admin);
        $this->info(__('Two-factor sign-in of :name is off. They set it up again at their next sign-in.', ['name' => $admin->name]));

        return self::SUCCESS;
    }
}
