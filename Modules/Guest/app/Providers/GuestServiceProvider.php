<?php

namespace Modules\Guest\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\ModuleAccess;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingType;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Guest\Models\TravelAgent;
use Modules\Guest\Policies\CompanyPolicy;
use Modules\Guest\Policies\GuestPolicy;
use Modules\Guest\Policies\TravelAgentPolicy;
use Modules\Guest\Services\GuestLookupService;
use Modules\Guest\Services\GuestRegistryService;
use Modules\Guest\Services\IdNumberHasher;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GuestServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Guest';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'guest';

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

        $this->app->singleton(GuestLookup::class, GuestLookupService::class);
        $this->app->singleton(GuestRegistry::class, GuestRegistryService::class);
        $this->app->singleton(IdNumberHasher::class, fn (): IdNumberHasher => new IdNumberHasher('guest-id|'.config('app.key')));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['guest' => Guest::class, 'company' => Company::class, 'travel_agent' => TravelAgent::class]);

        Gate::policy(Guest::class, GuestPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(TravelAgent::class, TravelAgentPolicy::class);

        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
        $this->app->make(Settings::class)->define(new SettingDefinition('guest.default_calling_code', 'Default country calling code', SettingType::Text, '880',
            group: 'Guests', help: 'Added to local phone numbers (e.g. 01711-000000 becomes +8801711000000). Digits only.', rules: ['regex:/^\d{1,4}$/']));
        $this->shareQuickSearch();
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $desk = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent];
        $managers = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager];

        $permissions->register('Guests', [
            new PermissionDefinition('guest.guest.view', 'View guests', $desk),
            new PermissionDefinition('guest.guest.create', 'Create guests', $desk),
            new PermissionDefinition('guest.guest.update', 'Change guests', $desk),
            new PermissionDefinition('guest.guest.view-id', 'See guest ID numbers and ID documents', [...$managers, DefaultRole::FrontDeskAgent]),
            new PermissionDefinition('guest.guest.merge', 'Merge duplicate guests', $managers),
            new PermissionDefinition('guest.guest.blacklist', 'Blacklist guests', $managers),
        ]);

        $permissions->register('Companies & travel agents', [
            new PermissionDefinition('guest.company.view', 'View companies', [...$desk, DefaultRole::Accountant]),
            new PermissionDefinition('guest.company.manage', 'Change companies', [...$managers, DefaultRole::Accountant]),
            new PermissionDefinition('guest.travel-agent.view', 'View travel agents', [...$desk, DefaultRole::Accountant]),
            new PermissionDefinition('guest.travel-agent.manage', 'Change travel agents', [...$managers, DefaultRole::Accountant]),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('guests', 'Guests', 'bi-person-vcard', order: 300);
        $menu->add(new MenuItem('guest.guests', 'Guests', route: 'guest.guests.index', parent: 'guests', order: 10,
            permission: 'guest.guest.view', module: 'guest', active: 'guest.guests.*'));
        $menu->add(new MenuItem('guest.companies', 'Companies', route: 'guest.companies.index', parent: 'guests', order: 20,
            permission: 'guest.company.view', module: 'guest', active: 'guest.companies.*'));
        $menu->add(new MenuItem('guest.travel-agents', 'Travel agents', route: 'guest.travel-agents.index', parent: 'guests', order: 30,
            permission: 'guest.travel-agent.view', module: 'guest', active: 'guest.travel-agents.*'));
    }

    /**
     * The navbar quick search (guests for now; reservations join later).
     */
    private function shareQuickSearch(): void
    {
        View::composer('layouts.partials.navbar', function (\Illuminate\View\View $view): void {
            if (Auth::guard('web')->user()?->can('guest.guest.view') && $this->app->make(ModuleAccess::class)->enabled('guest')) {
                $view->with('quickSearch', ['url' => route('guest.guests.index'), 'placeholder' => __('Search guests…')]);
            }
        });
    }
}
