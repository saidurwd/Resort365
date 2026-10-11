<?php

namespace Modules\Inventory\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\Enums\SequenceReset;
use Modules\Inventory\Console\SeedUnitsCommand;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Policies\CatalogPolicy;
use Modules\Inventory\Policies\ItemPolicy;
use Modules\Inventory\Policies\StorePolicy;
use Modules\Inventory\Services\ItemCatalogService;
use Modules\Inventory\Services\StoreDirectoryService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Inventory';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'inventory';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SeedUnitsCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(ItemCatalog::class, ItemCatalogService::class);
        $this->app->bind(StoreDirectory::class, StoreDirectoryService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['item' => Item::class, 'item_category' => ItemCategory::class, 'unit' => Unit::class, 'store' => Store::class]);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Unit::class, CatalogPolicy::class);
        Gate::policy(ItemCategory::class, CatalogPolicy::class);
        Gate::policy(Store::class, StorePolicy::class);

        $this->app->make(DocumentNumbers::class)->register(new DocumentType('item', 'Item SKU', 'ITM', '{PREFIX}-{SEQ:5}', SequenceReset::Never));

        $keeper = DefaultRole::StoreKeeper;
        $buyer = DefaultRole::ProcurementOfficer;
        $gm = DefaultRole::GeneralManager;
        $viewers = [$gm, $keeper, $buyer, DefaultRole::FnbManager, DefaultRole::Chef, DefaultRole::Bartender, DefaultRole::HousekeepingSupervisor, DefaultRole::MaintenanceTechnician, DefaultRole::Accountant];

        $this->app->make(PermissionRegistry::class)->register('Inventory', [
            new PermissionDefinition('inventory.item.view', 'View items, units and categories', $viewers),
            new PermissionDefinition('inventory.item.manage', 'Add and change items', [$gm, $keeper, $buyer]),
            new PermissionDefinition('inventory.catalog.manage', 'Change units and item categories', [$gm, $keeper, $buyer]),
            new PermissionDefinition('inventory.item.import', 'Import items from a CSV file', [$gm, $keeper, $buyer]),
            new PermissionDefinition('inventory.store.view', 'View stores', $viewers),
            new PermissionDefinition('inventory.store.manage', 'Add and change stores', [$gm, $keeper]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('inventory', 'Inventory', 'bi-box-seam', order: 170);
        $menu->add(new MenuItem('inventory.items', 'Items', route: 'inventory.items.index', parent: 'inventory', order: 10, permission: 'inventory.item.view', module: 'inventory', active: 'inventory.items.*'));
        $menu->add(new MenuItem('inventory.categories', 'Categories', route: 'inventory.categories.index', parent: 'inventory', order: 20, permission: 'inventory.item.view', module: 'inventory', active: 'inventory.categories.*'));
        $menu->add(new MenuItem('inventory.units', 'Units', route: 'inventory.units.index', parent: 'inventory', order: 30, permission: 'inventory.item.view', module: 'inventory', active: 'inventory.units.*'));
        $menu->add(new MenuItem('inventory.stores', 'Stores', route: 'inventory.stores.index', parent: 'inventory', order: 40, permission: 'inventory.store.view', module: 'inventory', active: 'inventory.stores.*'));
        $menu->add(new MenuItem('inventory.import', 'Import items', route: 'inventory.import.create', parent: 'inventory', order: 50, permission: 'inventory.item.import', module: 'inventory', active: 'inventory.import.*'));
    }
}
