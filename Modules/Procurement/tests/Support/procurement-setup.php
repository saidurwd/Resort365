<?php

use App\Support\Authorization\DefaultRole;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\IAM\Models\User;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Procurement\Actions\SaveVendor;
use Modules\Procurement\Actions\SaveVendorPrice;
use Modules\Procurement\Models\Vendor;
use Modules\Property\Models\Department;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

/** Units, the approval workflows (new tenants get them when created through CreateTenant, factories do not). */
function pcPrepare(): void
{
    booking(function (): void {
        app(SeedStandardUnits::class)->handle();
        app(SeedApprovalWorkflows::class)->handle();
    });
}

function pcStore(string $code = 'MAIN'): Store
{
    return booking(fn (): Store => Store::query()->where('code', $code)->first() ?? app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => $code, 'name' => $code.' store', 'kind' => 'main']));
}

function pcItem(string $name, string $reorder = '0', string $cost = '100'): Item
{
    return booking(function () use ($name, $reorder, $cost): Item {
        $category = ItemCategory::query()->firstOrCreate(['name' => 'Food'], ['sort_order' => 0, 'is_active' => true, 'account_class' => 'food']);

        return Item::query()->where('name', $name)->first() ?? app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => $name, 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id'), 'reorder_level' => $reorder, 'standard_cost' => $cost]);
    });
}

function pcStock(Store $store, Item $item, string $quantity, string $cost = '100'): void
{
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $item->id, $quantity, $cost)));
}

function pcVendor(string $name = 'Fresh Farm', ?string $email = 'sales@freshfarm.example', string $status = 'active'): Vendor
{
    return booking(fn (): Vendor => app(SaveVendor::class)->handle(null, ['name' => $name, 'email' => $email, 'currency' => 'BDT', 'payment_terms_days' => 14, 'status' => $status]));
}

/** @param  array<string, string>  $extra */
function pcPrice(Vendor $vendor, Item $item, string $price, array $extra = []): void
{
    booking(fn () => app(SaveVendorPrice::class)->handle($vendor, null, ['item_id' => $item->id, 'price' => $price, ...$extra]));
}

function pcDepartment(): Department
{
    return booking(fn (): Department => Department::factory()->create());
}

function pcUser(DefaultRole $role): User
{
    return tenantUserAs(tenant('sunrise'), $role);
}
