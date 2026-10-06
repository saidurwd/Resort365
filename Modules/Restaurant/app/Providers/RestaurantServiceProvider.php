<?php

namespace Modules\Restaurant\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Contracts\FolioReferenceLinks;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\FrontOffice\Contracts\NightAuditBlockers;
use Modules\Restaurant\Auth\StationDisplay;
use Modules\Restaurant\Broadcasting\RestaurantChannels;
use Modules\Restaurant\Http\Middleware\EnsureKdsStation;
use Modules\Restaurant\Http\Middleware\EnsurePosStaff;
use Modules\Restaurant\Http\Middleware\EnsurePosTerminal;
use Modules\Restaurant\Models\ComboComponent;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\DiscountLimit;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\KotLine;
use Modules\Restaurant\Models\ManagerApproval;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem as MenuItemModel;
use Modules\Restaurant\Models\MenuItemVariant;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosBillLine;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Models\Printer;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Policies\MenuItemPolicy;
use Modules\Restaurant\Policies\OutletPolicy;
use Modules\Restaurant\Policies\PosBillPolicy;
use Modules\Restaurant\Policies\PosOrderPolicy;
use Modules\Restaurant\Policies\PrinterPolicy;
use Modules\Restaurant\Policies\TableReservationPolicy;
use Modules\Restaurant\Services\KdsContext;
use Modules\Restaurant\Services\KdsDevice;
use Modules\Restaurant\Services\OpenSessionsBlocker;
use Modules\Restaurant\Services\PosContext;
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

    public function register(): void
    {
        parent::register();

        $this->app->scoped(PosContext::class);
        $this->app->scoped(KdsContext::class);

        // The `kds` guard: a kitchen display signed in with its station's device token (Step 3.5).
        config(['auth.guards.kds' => ['driver' => 'kds-display']]);
    }

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
            'pos_session' => PosSession::class,
            'manager_approval' => ManagerApproval::class,
            'pos_order' => PosOrder::class,
            'pos_order_line' => PosOrderLine::class,
            'kot' => Kot::class,
            'kot_line' => KotLine::class,
            'pos_bill' => PosBill::class,
            'pos_bill_line' => PosBillLine::class,
            'pos_payment' => PosPayment::class,
            'discount_limit' => DiscountLimit::class,
            'package_redemption' => PackageRedemption::class,
            'table_reservation' => TableReservation::class,
        ]);
        Gate::policy(PosOrder::class, PosOrderPolicy::class);
        Gate::policy(PosBill::class, PosBillPolicy::class);
        Gate::policy(TableReservation::class, TableReservationPolicy::class);
        Gate::policy(MenuItemModel::class, MenuItemPolicy::class);
        $this->app->make(Router::class)->aliasMiddleware('pos.terminal', EnsurePosTerminal::class);
        $this->app->make(Router::class)->aliasMiddleware('pos.staff', EnsurePosStaff::class);
        $this->app->make(Router::class)->aliasMiddleware('kds.station', EnsureKdsStation::class);
        Auth::viaRequest('kds-display', fn (Request $request): ?StationDisplay => ($station = app(KdsDevice::class)->fromRequest($request)) instanceof KitchenStation ? new StationDisplay($station) : null);
        RestaurantChannels::register();
        $this->app->make(NightAuditBlockers::class)->register(OpenSessionsBlocker::class);
        $this->app->make(FolioReferenceLinks::class)->register('pos_bill', fn (int $billId): ?array => ($bill = PosBill::query()->find($billId)) instanceof PosBill
            ? ['label' => __('Receipt :no', ['no' => $bill->bill_no]), 'url' => route('restaurant.bills.receipt', $bill)] : null);
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
            new PermissionDefinition('restaurant.pos.use', 'Work on the POS', [...$managers, DefaultRole::Waiter, DefaultRole::Bartender, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.session.manage', 'Open and close POS sessions', [...$managers, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.session.view', 'View every POS session and its reports', [...$managers, DefaultRole::Accountant]),
            new PermissionDefinition('restaurant.session.approve-variance', 'Approve closing a session with a large cash difference', $managers),
            new PermissionDefinition('restaurant.order.take', 'Take orders and send them to the kitchen', [...$managers, DefaultRole::Waiter, DefaultRole::Bartender, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.order.void', 'Void items sent to the kitchen (others need a manager\'s PIN)', $managers),
            new PermissionDefinition('restaurant.order.open-item', 'Price open items', [...$managers, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.bill.settle', 'Take payments on restaurant bills', [...$managers, DefaultRole::OutletCashier, DefaultRole::Bartender]),
            new PermissionDefinition('restaurant.bill.reopen', 'Reopen a printed bill (others need a manager\'s PIN)', $managers),
            new PermissionDefinition('restaurant.bill.comp', 'Make a bill complimentary (others need a manager\'s PIN)', $managers),
            new PermissionDefinition('restaurant.bill.void', 'Void a settled bill on its business date (others need a manager\'s PIN)', $managers),
            new PermissionDefinition('restaurant.discount.approve', 'Give or approve any discount', $managers),
            new PermissionDefinition('restaurant.discount-limit.manage', 'Set the discount limit of each role', $managers),
            new PermissionDefinition('restaurant.package.redeem', 'Redeem guests\' meal plans at the outlets', [...$managers, DefaultRole::Waiter, DefaultRole::Bartender, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.package.override', 'Redeem more meals than a meal plan includes (others need a manager\'s PIN)', $managers),
            new PermissionDefinition('restaurant.order.staff-meal', 'Open staff meal orders', [...$managers, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.reservation.view', 'View restaurant table reservations', [...$managers, DefaultRole::OutletCashier, DefaultRole::Waiter]),
            new PermissionDefinition('restaurant.reservation.manage', 'Book, change and cancel restaurant table reservations', [...$managers, DefaultRole::OutletCashier]),
            new PermissionDefinition('restaurant.report.view', 'View restaurant sales and exception reports', [...$managers, DefaultRole::Accountant]),
            new PermissionDefinition('restaurant.kds.use', 'Open a station\'s kitchen display and move tickets on', [...$managers, DefaultRole::Chef, DefaultRole::Bartender]),
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
        $menu->add(new MenuItem('restaurant.sessions', 'POS sessions', route: 'restaurant.sessions.index', parent: 'restaurant', order: 18,
            permission: 'restaurant.session.view', module: 'restaurant', active: 'restaurant.sessions.*'));
        $menu->add(new MenuItem('restaurant.kds', 'Kitchen display', route: 'kds.stations', parent: 'restaurant', order: 19,
            permission: 'restaurant.kds.use', module: 'restaurant', active: 'kds.*'));
        $menu->add(new MenuItem('restaurant.discount-limits', 'Discount limits', route: 'restaurant.discount-limits.index', parent: 'restaurant', order: 25,
            permission: 'restaurant.discount-limit.manage', module: 'restaurant', active: 'restaurant.discount-limits.*'));
        $menu->add(new MenuItem('restaurant.reservations', 'Table reservations', route: 'restaurant.reservations.index', parent: 'restaurant', order: 12,
            permission: 'restaurant.reservation.view', module: 'restaurant', active: 'restaurant.reservations.*'));
        $menu->add(new MenuItem('restaurant.reports', 'Reports', route: 'restaurant.reports.sales', parent: 'restaurant', order: 22,
            permission: 'restaurant.report.view', module: 'restaurant', active: 'restaurant.reports.*'));
        $menu->add(new MenuItem('restaurant.printers', 'Printers', route: 'restaurant.printers.index', parent: 'restaurant', order: 20,
            permission: 'restaurant.outlet.view', module: 'restaurant', active: 'restaurant.printers.*'));
        $menu->add(new MenuItem('restaurant.access', 'Outlet access', route: 'restaurant.access.index', parent: 'restaurant', order: 30,
            permission: 'restaurant.access.manage', module: 'restaurant', active: 'restaurant.access.*'));

        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.menu_languages', 'Menu languages', SettingType::Text, 'en,bn', SettingScope::Tenant,
            'Restaurant', help: 'Language codes menus are written in, separated by commas (en = English, bn = Bangla). English is always included.',
            rules: ['regex:/^\\s*[a-z]{2}(\\s*,\\s*[a-z]{2})*\\s*$/']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.pos_auto_lock_minutes', 'Lock the POS after (minutes idle)', SettingType::Integer, 3,
            SettingScope::Property, 'Restaurant', help: 'The person on a POS terminal is signed out after this many minutes without using it. 0 = never.', rules: ['min:0', 'max:120']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.session_variance_limit', 'Cash difference needing a manager', SettingType::Decimal, '500',
            SettingScope::Property, 'Restaurant', help: 'Closing a POS session with a larger cash over or short needs a manager\'s PIN.', rules: ['min:0']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.service_charge_code', 'Service charge tax code', SettingType::Text, 'SC', SettingScope::Tenant,
            'Restaurant', help: 'The code of the tax (Setup → Taxes) that is the service charge: bills show it apart from the other taxes.', rules: ['max:20']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.room_charge_code', 'Charge code for restaurant room charges', SettingType::Text, 'FNB', SettingScope::Tenant,
            'Restaurant', help: 'The charge code (Setup → Charge codes) restaurant bills charged to a room are posted with on the guest\'s folio.', rules: ['max:20']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.breakfast_until', 'Breakfast is served until', SettingType::Text, '11:00', SettingScope::Property,
            'Restaurant', help: 'Meal plans: before this time the POS suggests breakfast (HH:MM).', rules: ['regex:/^([01]\d|2[0-3]):[0-5]\d$/']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.lunch_until', 'Lunch is served until', SettingType::Text, '16:00', SettingScope::Property,
            'Restaurant', help: 'Meal plans: before this time (after breakfast) the POS suggests lunch, after it dinner (HH:MM).', rules: ['regex:/^([01]\d|2[0-3]):[0-5]\d$/']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.kds_warn_minutes', 'Kitchen ticket turns amber after (minutes)', SettingType::Integer, 10,
            SettingScope::Property, 'Restaurant', help: 'On the kitchen display, a ticket waiting this long is shown in amber.', rules: ['min:1', 'max:240']));
        $this->app->make(Settings::class)->define(new SettingDefinition('restaurant.kds_late_minutes', 'Kitchen ticket turns red after (minutes)', SettingType::Integer, 20,
            SettingScope::Property, 'Restaurant', help: 'On the kitchen display, a ticket waiting this long is shown in red as late.', rules: ['min:1', 'max:240']));
    }
}
