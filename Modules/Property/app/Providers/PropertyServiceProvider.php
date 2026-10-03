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
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\Contracts\RoomUsage;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;
use Modules\Property\Policies\AmenityPolicy;
use Modules\Property\Policies\CottagePolicy;
use Modules\Property\Policies\CottageTypePolicy;
use Modules\Property\Policies\DepartmentPolicy;
use Modules\Property\Policies\PropertyPolicy;
use Modules\Property\Policies\RoomPolicy;
use Modules\Property\Policies\RoomTypePolicy;
use Modules\Property\Services\InventoryCatalogService;
use Modules\Property\Services\NoRoomUsage;
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
        $this->app->singleton(InventoryCatalog::class, InventoryCatalogService::class);
        // The Reservation module replaces this with the real check (TODO(step-1.6)).
        $this->app->bindIf(RoomUsage::class, NoRoomUsage::class, shared: true);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'property' => Property::class,
            'cottage_type' => CottageType::class,
            'room_type' => RoomType::class,
            'cottage' => Cottage::class,
            'room' => Room::class,
            'amenity' => Amenity::class,
            'department' => Department::class,
        ]);

        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(CottageType::class, CottageTypePolicy::class);
        Gate::policy(RoomType::class, RoomTypePolicy::class);
        Gate::policy(Cottage::class, CottagePolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(Amenity::class, AmenityPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);

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

        $frontOffice = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent, DefaultRole::HousekeepingSupervisor];

        $permissions->register('Cottages & rooms', [
            new PermissionDefinition('property.cottage.view', 'View cottages and cottage types', $frontOffice),
            new PermissionDefinition('property.cottage.manage', 'Set up cottages and cottage types', [DefaultRole::GeneralManager]),
            new PermissionDefinition('property.room.view', 'View rooms and room types', $frontOffice),
            new PermissionDefinition('property.room.manage', 'Set up rooms and room types', [DefaultRole::GeneralManager]),
            new PermissionDefinition('property.amenity.view', 'View the amenities catalogue', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
            new PermissionDefinition('property.amenity.manage', 'Change the amenities catalogue', [DefaultRole::GeneralManager]),
        ]);

        $permissions->register('Departments', [
            new PermissionDefinition('property.department.view', 'View departments', [
                DefaultRole::GeneralManager, DefaultRole::HrManager, DefaultRole::Accountant, DefaultRole::StoreKeeper,
                DefaultRole::ProcurementOfficer, DefaultRole::PayrollOfficer,
            ]),
            new PermissionDefinition('property.department.manage', 'Change departments', [DefaultRole::GeneralManager, DefaultRole::HrManager]),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('property.properties', 'Properties', route: 'property.properties.index', parent: 'setup', order: 1,
            permission: 'property.property.view', module: 'property', active: 'property.properties.*'));
        $menu->add(new MenuItem('property.access', 'Property access', route: 'property.access.index', parent: 'setup', order: 15,
            permission: 'property.access.update', module: 'property', active: 'property.access.*'));
        $menu->add(new MenuItem('property.cottages', 'Cottages', route: 'property.cottages.index', parent: 'setup', order: 2,
            permission: 'property.cottage.view', module: 'property', active: 'property.cottages.*'));
        $menu->add(new MenuItem('property.rooms', 'Rooms', route: 'property.rooms.index', parent: 'setup', order: 3,
            permission: 'property.room.view', module: 'property', active: 'property.rooms.*'));
        $menu->add(new MenuItem('property.cottage-types', 'Cottage types', route: 'property.cottage-types.index', parent: 'setup', order: 4,
            permission: 'property.cottage.view', module: 'property', active: 'property.cottage-types.*'));
        $menu->add(new MenuItem('property.room-types', 'Room types', route: 'property.room-types.index', parent: 'setup', order: 5,
            permission: 'property.room.view', module: 'property', active: 'property.room-types.*'));
        $menu->add(new MenuItem('property.amenities', 'Amenities', route: 'property.amenities.index', parent: 'setup', order: 6,
            permission: 'property.amenity.view', module: 'property', active: 'property.amenities.*'));
        $menu->add(new MenuItem('property.departments', 'Departments', route: 'property.departments.index', parent: 'setup', order: 7,
            permission: 'property.department.view', module: 'property', active: 'property.departments.*'));
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
