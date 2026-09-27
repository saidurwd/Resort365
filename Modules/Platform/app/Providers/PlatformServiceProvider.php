<?php

namespace Modules\Platform\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Platform\Console\CreatePlatformAdminCommand;
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

    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('platform-login', fn (Request $request): Limit => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
