<?php

namespace Modules\Restaurant\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Models\Printer;
use Modules\Restaurant\Policies\OutletPolicy;
use Modules\Restaurant\Policies\PrinterPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class RestaurantServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Restaurant';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'restaurant';

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
            'outlet' => Outlet::class,
            'printer' => Printer::class,
            'kitchen_station' => KitchenStation::class,
            'pos_terminal' => PosTerminal::class,
            'dining_area' => DiningArea::class,
            'dining_table' => DiningTable::class,
        ]);
        Gate::policy(Outlet::class, OutletPolicy::class);
        Gate::policy(Printer::class, PrinterPolicy::class);

        $managers = [DefaultRole::GeneralManager, DefaultRole::FnbManager];

        $this->app->make(PermissionRegistry::class)->register('Restaurant', [
            new PermissionDefinition('restaurant.outlet.view', 'View outlets, stations, terminals and printers', [...$managers, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.outlet.manage', 'Set up outlets, stations, terminals and printers', $managers),
            new PermissionDefinition('restaurant.floor-plan.manage', 'Arrange dining areas and tables', $managers),
            new PermissionDefinition('restaurant.access.manage', 'Choose which staff work in which outlets', $managers),
            new PermissionDefinition('restaurant.outlet.access-all', 'Work in every outlet', [DefaultRole::GeneralManager]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('restaurant', 'Restaurant', 'bi-cup-hot', order: 170);
        $menu->add(new MenuItem('restaurant.outlets', 'Outlets', route: 'restaurant.outlets.index', parent: 'restaurant', order: 10,
            permission: 'restaurant.outlet.view', module: 'restaurant', active: 'restaurant.outlets.*'));
        $menu->add(new MenuItem('restaurant.printers', 'Printers', route: 'restaurant.printers.index', parent: 'restaurant', order: 20,
            permission: 'restaurant.outlet.view', module: 'restaurant', active: 'restaurant.printers.*'));
        $menu->add(new MenuItem('restaurant.access', 'Outlet access', route: 'restaurant.access.index', parent: 'restaurant', order: 30,
            permission: 'restaurant.access.manage', module: 'restaurant', active: 'restaurant.access.*'));
    }
}
