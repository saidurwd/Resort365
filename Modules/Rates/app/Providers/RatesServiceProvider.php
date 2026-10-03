<?php

namespace Modules\Rates\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Rates\Models\Rate;
use Modules\Rates\Models\RateOverride;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\RateRestriction;
use Modules\Rates\Models\Season;
use Modules\Rates\Models\SeasonPeriod;
use Modules\Rates\Policies\RatePlanPolicy;
use Modules\Rates\Policies\SeasonPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class RatesServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Rates';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'rates';

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

        Relation::morphMap([
            'season' => Season::class,
            'season_period' => SeasonPeriod::class,
            'rate_plan' => RatePlan::class,
            'rate' => Rate::class,
            'rate_override' => RateOverride::class,
            'rate_restriction' => RateRestriction::class,
        ]);

        Gate::policy(Season::class, SeasonPolicy::class);
        Gate::policy(RatePlan::class, RatePlanPolicy::class);

        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
        $this->app->make(Settings::class)->define(new SettingDefinition('rates.weekend_days', 'Weekend days', SettingType::Text, '5,6', SettingScope::Property, 'Rates',
            help: 'Days that use weekend rates: 1 = Monday … 7 = Sunday, separated by commas. Bangladesh: 5,6 (Friday and Saturday).',
            rules: ['regex:/^[1-7](,[1-7])*$/']));
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $view = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent];
        $manage = [DefaultRole::GeneralManager];

        $permissions->register('Rates', [
            new PermissionDefinition('rates.rate.view', 'View rates and the rate grid', $view),
            new PermissionDefinition('rates.rate.manage', 'Change rates, date prices and restrictions', $manage),
            new PermissionDefinition('rates.rate-plan.view', 'View rate plans', $view),
            new PermissionDefinition('rates.rate-plan.manage', 'Change rate plans', $manage),
            new PermissionDefinition('rates.season.view', 'View seasons', $view),
            new PermissionDefinition('rates.season.manage', 'Change seasons', $manage),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('rates', 'Rates', 'bi-tags', order: 350);
        $menu->add(new MenuItem('rates.grid', 'Rate grid', route: 'rates.grid', parent: 'rates', order: 10,
            permission: 'rates.rate.view', module: 'rates', active: 'rates.grid*'));
        $menu->add(new MenuItem('rates.rate-plans', 'Rate plans', route: 'rates.rate-plans.index', parent: 'rates', order: 20,
            permission: 'rates.rate-plan.view', module: 'rates', active: 'rates.rate-plans.*'));
        $menu->add(new MenuItem('rates.seasons', 'Seasons', route: 'rates.seasons.index', parent: 'rates', order: 30,
            permission: 'rates.season.view', module: 'rates', active: 'rates.seasons.*'));
    }
}
