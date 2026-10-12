<?php

use App\Support\Authorization\DefaultRole;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\Core\Contracts\ApprovalEngine;
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
use Modules\Procurement\Actions\ReceiveGoods;
use Modules\Procurement\Actions\SavePurchaseOrder;
use Modules\Procurement\Actions\SaveVendor;
use Modules\Procurement\Actions\SaveVendorBill;
use Modules\Procurement\Actions\SaveVendorPrice;
use Modules\Procurement\Actions\SubmitPurchaseOrder;
use Modules\Procurement\Actions\SubmitVendorBill;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\DTOs\NewVendorBill;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorBill;
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

/**
 * An approved purchase order (small orders need the accountant, large ones also the General Manager).
 *
 * @param  list<array{0: Item, 1: string, 2: string}>  $lines  item, quantity, unit price
 */
function pcApprovedOrder(array $lines, ?Vendor $vendor = null): PurchaseOrder
{
    $vendor ??= pcVendor();
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $data = new NewPurchaseOrder(
        bookingIds()['property'], $vendor->id, pcStore()->id,
        array_map(fn (array $line): array => ['item_id' => $line[0]->id, 'quantity' => $line[1], 'unit_price' => $line[2]], $lines), $buyer->id,
    );
    $order = booking(fn (): PurchaseOrder => app(SubmitPurchaseOrder::class)->handle(app(SavePurchaseOrder::class)->handle(null, $data), $buyer->id));

    if ($order->status === PurchaseOrderStatus::Pending) {
        booking(fn () => app(ApprovalEngine::class)->approve((int) $order->approval_request_id, pcUser(DefaultRole::Accountant)->id));

        if (booking(fn (): PurchaseOrderStatus => PurchaseOrder::query()->findOrFail($order->id)->status) === PurchaseOrderStatus::Pending) {
            booking(fn () => app(ApprovalEngine::class)->approve((int) $order->approval_request_id, pcUser(DefaultRole::GeneralManager)->id));
        }
    }

    return booking(fn (): PurchaseOrder => PurchaseOrder::query()->with('lines')->findOrFail($order->id));
}

/**
 * Receives lines of an approved order: order line index (0-based) => accepted quantity.
 *
 * @param  array<int, string>  $accepted
 */
function pcReceive(PurchaseOrder $order, array $accepted): GoodsReceipt
{
    $lines = [];

    foreach ($accepted as $index => $quantity) {
        $lines[] = ['purchase_order_line_id' => $order->lines[$index]->id, 'accepted_quantity' => $quantity];
    }

    return booking(fn (): GoodsReceipt => app(ReceiveGoods::class)->handle($order, $lines, pcUser(DefaultRole::StoreKeeper)->id));
}

/**
 * A goods bill: order line index => [quantity, unit price].
 *
 * @param  array<int, array{0: string, 1: string}>  $billed
 */
function pcBill(PurchaseOrder $order, array $billed, bool $override = false): VendorBill
{
    $lines = [];

    foreach ($billed as $index => [$quantity, $price]) {
        $lines[] = ['purchase_order_line_id' => $order->lines[$index]->id, 'quantity' => $quantity, 'unit_price' => $price];
    }

    return booking(fn (): VendorBill => app(SaveVendorBill::class)->handle(null, new NewVendorBill(bookingIds()['property'], $order->vendor_id, VendorBillKind::Purchase, $lines, pcUser(DefaultRole::ProcurementOfficer)->id, $order->id, overrideMatch: $override)));
}

/** Submits a bill and has the accountant approve it. */
function pcApproveBill(VendorBill $bill): VendorBill
{
    $bill = booking(fn (): VendorBill => app(SubmitVendorBill::class)->handle($bill, pcUser(DefaultRole::ProcurementOfficer)->id));

    if ($bill->status === VendorBillStatus::Pending) {
        booking(fn () => app(ApprovalEngine::class)->approve((int) $bill->approval_request_id, pcUser(DefaultRole::Accountant)->id));
    }

    return booking(fn (): VendorBill => VendorBill::query()->with('lines')->findOrFail($bill->id));
}

/** The order again, with its lines, read inside the tenant. */
function pcFresh(PurchaseOrder $order): PurchaseOrder
{
    return booking(fn (): PurchaseOrder => PurchaseOrder::query()->with('lines')->findOrFail($order->id));
}

/** The receipt again, with its lines, read inside the tenant. */
function pcFreshReceipt(GoodsReceipt $receipt): GoodsReceipt
{
    return booking(fn (): GoodsReceipt => GoodsReceipt::query()->with('lines')->findOrFail($receipt->id));
}
