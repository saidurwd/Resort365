<?php

namespace Modules\Inventory\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\ApprovalDocumentType;
use Modules\Core\DTOs\ApprovalStepDefinition;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SequenceReset;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Inventory\Console\SeedUnitsCommand;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StockFacts;
use Modules\Inventory\Contracts\StockIssuing;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Policies\CatalogPolicy;
use Modules\Inventory\Policies\CountPolicy;
use Modules\Inventory\Policies\ItemPolicy;
use Modules\Inventory\Policies\RequisitionPolicy;
use Modules\Inventory\Policies\StockPolicy;
use Modules\Inventory\Policies\StorePolicy;
use Modules\Inventory\Services\ItemCatalogService;
use Modules\Inventory\Services\RequisitionApprovalHandler;
use Modules\Inventory\Services\StockFactsService;
use Modules\Inventory\Services\StockIssuer;
use Modules\Inventory\Services\StockMovementService;
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
        $this->app->bind(StockMovementContract::class, StockMovementService::class);
        $this->app->bind(StockIssuing::class, StockIssuer::class);
        $this->app->bind(StockFacts::class, StockFactsService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['store_requisition' => StockRequisition::class, 'stock_issue' => StockIssue::class, 'stock_count' => StockCount::class, 'stock_transfer' => StockTransfer::class, 'item' => Item::class, 'item_category' => ItemCategory::class, 'unit' => Unit::class, 'store' => Store::class]);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Unit::class, CatalogPolicy::class);
        Gate::policy(ItemCategory::class, CatalogPolicy::class);
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(StockBalance::class, StockPolicy::class);
        Gate::policy(StockMovement::class, StockPolicy::class);
        Gate::policy(StockTransfer::class, StockPolicy::class);
        Gate::policy(StockRequisition::class, RequisitionPolicy::class);
        Gate::policy(StockCount::class, CountPolicy::class);

        $this->app->make(DocumentNumbers::class)->register(new DocumentType('item', 'Item SKU', 'ITM', '{PREFIX}-{SEQ:5}', SequenceReset::Never));
        $this->app->make(DocumentNumbers::class)->register(new DocumentType('stock_transfer', 'Stock transfer', 'TRF'));
        $this->app->make(DocumentNumbers::class)->register(new DocumentType('store_requisition', 'Store requisition', 'SR'));
        $this->app->make(DocumentNumbers::class)->register(new DocumentType('stock_issue', 'Stock issue', 'ISS'));
        $this->app->make(DocumentNumbers::class)->register(new DocumentType('stock_count', 'Stock count', 'SC'));
        $this->app->make(ApprovalRegistry::class)->register(new ApprovalDocumentType(
            'store_requisition', 'Store requisition', 'A department\'s request for items from a store. Approved before the store keeper issues it.',
            [new ApprovalStepDefinition('Department manager', 'inventory.requisition.approve', '0.00')],
        ), RequisitionApprovalHandler::class);
        $this->app->make(Settings::class)->define(new SettingDefinition('inventory.allow_negative_stock', 'Allow stock to go negative', SettingType::Boolean, false, SettingScope::Tenant, 'Inventory',
            help: 'Lets a store issue more than it holds, for kitchens whose receipts are entered late. The next receipt re-sets the average cost.'));

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
            new PermissionDefinition('inventory.stock.view', 'View stock on hand, movements and transfers', $viewers),
            new PermissionDefinition('inventory.stock.adjust', 'Record opening balances and adjust stock', [$gm, $keeper]),
            new PermissionDefinition('inventory.requisition.view', 'View store requisitions', [...$viewers, DefaultRole::Waiter]),
            new PermissionDefinition('inventory.requisition.create', 'Ask a store for items for a department', [$gm, $keeper, DefaultRole::FnbManager, DefaultRole::Chef, DefaultRole::Bartender, DefaultRole::HousekeepingSupervisor, DefaultRole::MaintenanceTechnician]),
            new PermissionDefinition('inventory.requisition.approve', 'Approve store requisitions', [$gm, DefaultRole::FnbManager, DefaultRole::HousekeepingSupervisor]),
            new PermissionDefinition('inventory.requisition.issue', 'Issue approved requisitions', [$gm, $keeper]),
            new PermissionDefinition('inventory.issue.create', 'Issue stock to a department without a requisition', [$gm, $keeper]),
            new PermissionDefinition('inventory.count.view', 'View physical stock counts', $viewers),
            new PermissionDefinition('inventory.count.manage', 'Start, enter, complete and cancel stock counts', [$gm, $keeper]),
            new PermissionDefinition('inventory.transfer.manage', 'Dispatch, receive and cancel stock transfers', [$gm, $keeper]),
            new PermissionDefinition('inventory.store.manage', 'Add and change stores', [$gm, $keeper]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('inventory', 'Inventory', 'bi-box-seam', order: 170);
        $menu->add(new MenuItem('inventory.items', 'Items', route: 'inventory.items.index', parent: 'inventory', order: 10, permission: 'inventory.item.view', module: 'inventory', active: 'inventory.items.*'));
        $menu->add(new MenuItem('inventory.stock', 'Stock on hand', route: 'inventory.stock.index', parent: 'inventory', order: 12, permission: 'inventory.stock.view', module: 'inventory', active: 'inventory.stock.*'));
        $menu->add(new MenuItem('inventory.movements', 'Movements', route: 'inventory.movements.index', parent: 'inventory', order: 14, permission: 'inventory.stock.view', module: 'inventory', active: 'inventory.movements.*'));
        $menu->add(new MenuItem('inventory.transfers', 'Transfers', route: 'inventory.transfers.index', parent: 'inventory', order: 16, permission: 'inventory.stock.view', module: 'inventory', active: 'inventory.transfers.*'));
        $menu->add(new MenuItem('inventory.adjust', 'Adjust and open stock', route: 'inventory.adjustments.create', parent: 'inventory', order: 18, permission: 'inventory.stock.adjust', module: 'inventory', active: ['inventory.adjustments.*', 'inventory.openings.*']));
        $menu->add(new MenuItem('inventory.requisitions', 'Requisitions', route: 'inventory.requisitions.index', parent: 'inventory', order: 13, permission: 'inventory.requisition.view', module: 'inventory', active: 'inventory.requisitions.*'));
        $menu->add(new MenuItem('inventory.issues', 'Direct issue', route: 'inventory.issues.create', parent: 'inventory', order: 17, permission: 'inventory.issue.create', module: 'inventory', active: 'inventory.issues.*'));
        $menu->add(new MenuItem('inventory.counts', 'Stock counts', route: 'inventory.counts.index', parent: 'inventory', order: 19, permission: 'inventory.count.view', module: 'inventory', active: 'inventory.counts.*'));
        $menu->add(new MenuItem('inventory.categories', 'Categories', route: 'inventory.categories.index', parent: 'inventory', order: 20, permission: 'inventory.item.view', module: 'inventory', active: 'inventory.categories.*'));
        $menu->add(new MenuItem('inventory.units', 'Units', route: 'inventory.units.index', parent: 'inventory', order: 30, permission: 'inventory.item.view', module: 'inventory', active: 'inventory.units.*'));
        $menu->add(new MenuItem('inventory.stores', 'Stores', route: 'inventory.stores.index', parent: 'inventory', order: 40, permission: 'inventory.store.view', module: 'inventory', active: 'inventory.stores.*'));
        $menu->add(new MenuItem('inventory.import', 'Import items', route: 'inventory.import.create', parent: 'inventory', order: 50, permission: 'inventory.item.import', module: 'inventory', active: 'inventory.import.*'));
    }
}
