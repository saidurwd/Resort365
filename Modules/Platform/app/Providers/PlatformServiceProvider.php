<?php

namespace Modules\Platform\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Contracts\Announcements;
use Modules\Core\Contracts\Impersonation;
use Modules\Core\Contracts\PlanLimits;
use Modules\Core\Contracts\SubscriptionAccount;
use Modules\Platform\Console\CreatePlatformAdminCommand;
use Modules\Platform\Console\PurgeTenantsCommand;
use Modules\Platform\Console\ResetTwoFactorCommand;
use Modules\Platform\Console\RunBillingCommand;
use Modules\Platform\Console\SeedPlansCommand;
use Modules\Platform\Contracts\PaymentGateway;
use Modules\Platform\Services\ManualGateway;
use Modules\Platform\Services\PlatformAnnouncements;
use Modules\Platform\Services\PlatformImpersonation;
use Modules\Platform\Services\TenantPlanLimits;
use Modules\Platform\Services\TenantSubscriptionAccount;
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
        RunBillingCommand::class,
        PurgeTenantsCommand::class,
        ResetTwoFactorCommand::class,
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

        // Subscriptions and platform invoices (Step 7.4): manual invoicing until a card gateway arrives.
        $this->app->bind(PaymentGateway::class, ManualGateway::class);
        $this->app->bind(SubscriptionAccount::class, TenantSubscriptionAccount::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('platform:billing')->dailyAt('00:30')->withoutOverlapping()->onOneServer();
            $schedule->command('platform:purge-tenants')->dailyAt('04:00')->withoutOverlapping()->onOneServer();
        });

        // Sign-ups per address and hour (Step 7.4).
        RateLimiter::for('signup', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('platform-two-factor', fn (Request $request): Limit => Limit::perMinute(5)->by($request->session()->getId().'|'.$request->ip()));
        RateLimiter::for('platform-login', fn (Request $request): Limit => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
