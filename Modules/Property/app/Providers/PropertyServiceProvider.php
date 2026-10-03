<?php

namespace Modules\Property\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Modules\Core\Contracts\Settings;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\Models\Property;
use Modules\Property\Policies\PropertyPolicy;
use Modules\Property\Services\PropertyAccessService;
use Modules\Property\Services\PropertyDirectoryService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PropertyServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Property';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'property';

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

        $this->app->singleton(PropertyAccess::class, PropertyAccessService::class);
        $this->app->singleton(PropertyDirectory::class, PropertyDirectoryService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['property' => Property::class]);
        Gate::policy(Property::class, PropertyPolicy::class);

        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
        $this->shareSwitcher();
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $permissions->register('Properties', [
            new PermissionDefinition('property.property.view', 'View properties', [DefaultRole::GeneralManager]),
            new PermissionDefinition('property.property.create', 'Create properties'),
            new PermissionDefinition('property.property.update', 'Change properties', [DefaultRole::GeneralManager]),
            new PermissionDefinition(PropertyAccessService::ACCESS_ALL, 'Work in every property (no assignment needed)', [DefaultRole::Auditor]),
            new PermissionDefinition('property.access.update', 'Assign users to properties'),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('property.properties', 'Properties', route: 'property.properties.index', parent: 'setup', order: 5,
            permission: 'property.property.view', module: 'property', active: 'property.properties.*'));
        $menu->add(new MenuItem('property.access', 'Property access', route: 'property.access.index', parent: 'setup', order: 15,
            permission: 'property.access.update', module: 'property', active: 'property.access.*'));
    }

    /**
     * Property switcher and business-date badge for the navbar.
     */
    private function shareSwitcher(): void
    {
        View::composer('layouts.partials.navbar', function (\Illuminate\View\View $view): void {
            $context = $this->app->make(PropertyContext::class);

            if (! $context->isRestricted()) {
                return;
            }

            $current = $this->app->make(PropertyDirectory::class)->current();

            $view->with('propertySwitcher', [
                'current' => $current,
                'businessDate' => $current !== null
                    ? Carbon::parse($current->businessDate)->format((string) $this->app->make(Settings::class)->get('core.date_format'))
                    : null,
                'options' => $context->accessible(),
                'switchUrl' => fn (int $id): string => route('property.switch', ['property' => $id]),
            ]);
        });
    }
}
