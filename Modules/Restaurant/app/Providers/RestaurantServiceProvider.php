<?php

namespace Modules\Restaurant\Providers;

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
use Modules\Restaurant\Models\ComboComponent;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem as MenuItemModel;
use Modules\Restaurant\Models\MenuItemVariant;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Models\Printer;
use Modules\Restaurant\Policies\MenuItemPolicy;
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
            'menu_category' => MenuCategory::class,
            'menu_item' => MenuItemModel::class,
            'menu_item_variant' => MenuItemVariant::class,
            'modifier_group' => ModifierGroup::class,
            'modifier' => Modifier::class,
            'combo_component' => ComboComponent::class,
            'menu_schedule' => MenuSchedule::class,
            'outlet_menu_item' => OutletMenuItem::class,
        ]);
        Gate::policy(MenuItemModel::class, MenuItemPolicy::class);
        Gate::policy(Outlet::class, OutletPolicy::class);
        Gate::policy(Printer::class, PrinterPolicy::class);

        $managers = [DefaultRole::GeneralManager, DefaultRole::FnbManager];

        $this->app->make(PermissionRegistry::class)->register('Restaurant', [
            new PermissionDefinition('restaurant.outlet.view', 'View outlets, stations, terminals and printers', [...$managers, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.outlet.manage', 'Set up outlets, stations, terminals and printers', $managers),
            new PermissionDefinition('restaurant.floor-plan.manage', 'Arrange dining areas and tables', $managers),
            new PermissionDefinition('restaurant.access.manage', 'Choose which staff work in which outlets', $managers),
            new PermissionDefinition('restaurant.outlet.access-all', 'Work in every outlet', [DefaultRole::GeneralManager]),
            new PermissionDefinition('restaurant.menu.view', 'View the menu', [...$managers, DefaultRole::OutletCashier, DefaultRole::Chef]),
            new PermissionDefinition('restaurant.menu.manage', 'Change the menu (items, categories, modifiers, import)', $managers),
            new PermissionDefinition('restaurant.price.manage', 'Set outlet prices, schedules and sold-out items', $managers),
            new PermissionDefinition('restaurant.menu.mark-sold-out', 'Mark items sold out (86)', [DefaultRole::Chef, DefaultRole::Bartender, DefaultRole::OutletCashier]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('restaurant', 'Restaurant', 'bi-cup-hot', order: 170);
        $menu->add(new MenuItem('restaurant.outlets', 'Outlets', route: 'restaurant.outlets.index', parent: 'restaurant', order: 10,
            permission: 'restaurant.outlet.view', module: 'restaurant', active: 'restaurant.outlets.*'));
        $menu->add(new MenuItem('restaurant.menu', 'Menu', route: 'restaurant.menu.items.index', parent: 'restaurant', order: 15,
            permission: 'restaurant.menu.view', module: 'restaurant', active: ['restaurant.menu.items.*', 'restaurant.menu.import*']));
        $menu->add(new MenuItem('restaurant.menu-categories', 'Menu categories', route: 'restaurant.menu.categories.index', parent: 'restaurant', order: 16,
            permission: 'restaurant.menu.view', module: 'restaurant', active: 'restaurant.menu.categories.*'));
        $menu->add(new MenuItem('restaurant.modifiers', 'Modifiers', route: 'restaurant.menu.modifiers.index', parent: 'restaurant', order: 17,
            permission: 'restaurant.menu.view', module: 'restaurant', active: 'restaurant.menu.modifiers.*'));
        $menu->add(new MenuItem('restaurant.printers', 'Printers', route: 'restaurant.printers.index', parent: 'restaurant', order: 20,
            permission: 'restaurant.outlet.view', module: 'restaurant', active: 'restaurant.printers.*'));
        $menu->add(new MenuItem('restaurant.access', 'Outlet access', route: 'restaurant.access.index', parent: 'restaurant', order: 30,
            permission: 'restaurant.access.manage', module: 'restaurant', active: 'restaurant.access.*'));

        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.menu_languages', 'Menu languages', SettingType::Text, 'en,bn', SettingScope::Tenant,
            'Restaurant', help: 'Language codes menus are written in, separated by commas (en = English, bn = Bangla). English is always included.',
            rules: ['regex:/^\\s*[a-z]{2}(\\s*,\\s*[a-z]{2})*\\s*$/']));
    }
}
