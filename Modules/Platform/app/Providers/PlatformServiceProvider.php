<?php

namespace Modules\Platform\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Contracts\Announcements;
use Modules\Core\Contracts\Impersonation;
use Modules\Core\Contracts\PlanLimits;
use Modules\Platform\Console\CreatePlatformAdminCommand;
use Modules\Platform\Console\SeedPlansCommand;
use Modules\Platform\Services\PlatformAnnouncements;
use Modules\Platform\Services\PlatformImpersonation;
use Modules\Platform\Services\TenantPlanLimits;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PlatformServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Platform';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'platform';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        CreatePlatformAdminCommand::class,
        SeedPlansCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Plans give Core's contracts their answers (Step 7.3): limits, announcements and platform admins signing in as a user.
        $this->app->singleton(TenantPlanLimits::class);
        $this->app->singleton(PlanLimits::class, fn ($app): TenantPlanLimits => $app->make(TenantPlanLimits::class));
        $this->app->singleton(Announcements::class, PlatformAnnouncements::class);
        $this->app->singleton(Impersonation::class, PlatformImpersonation::class);
    }

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('platform-login', fn (Request $request): Limit => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
