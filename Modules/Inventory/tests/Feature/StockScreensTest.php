<?php

/*
| Stock screens (Step 5.3): stock on hand with its low-stock flag, the movement history, opening balances,
| adjustments, transfers, reversals, and who may do what.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Enums\TransferStatus;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

function skStore(string $code): Store
{
    return booking(fn (): Store => app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => $code, 'name' => ucfirst(strtolower($code)).' store', 'kind' => 'main']));
}

function skItem(string $name, string $reorder = '0'): Item
{
    return booking(function () use ($name, $reorder): Item {
        $category = ItemCategory::query()->firstOrCreate(['name' => 'Food'], ['sort_order' => 0, 'is_active' => true]);

        return app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => $name, 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id'), 'reorder_level' => $reorder]);
    });
}

function skReceive(Store $store, Item $item, string $quantity, string $cost): void
{
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $item->id, $quantity, $cost)));
}

it('shows stock on hand per store with value, average cost and the low-stock flag', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $store = skStore('MAIN');
    $rice = skItem('Rice', '50');
    $sugar = skItem('Sugar', '10');
    skReceive($store, $rice, '40', '100');
    skReceive($store, $sugar, '30', '90');

    get(tenantUrl('sunrise', '/inventory/stock?store='.$store->id))->assertOk()
        ->assertSeeHtml('data-stock-item="'.$rice->sku.'"')->assertSeeHtml('data-low')->assertSee('4,000.00')->assertSee('2,700.00')
        ->assertSee('6,700.00')->assertSeeHtml('data-openings')->assertSeeHtml('data-adjust');
    get(tenantUrl('sunrise', '/inventory/stock?store='.$store->id.'&low=1'))->assertOk()->assertSeeHtml('data-stock-item="'.$rice->sku.'"')->assertDontSeeHtml('data-stock-item="'.$sugar->sku.'"');
});

it('lists the movement history with filters, and reverses a movement', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $store = skStore('MAIN');
    $rice = skItem('Rice');
    $sugar = skItem('Sugar');
    skReceive($store, $rice, '10', '100');
    skReceive($store, $sugar, '5', '90');
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Issue, $store->id, $rice->id, '2')));

    get(tenantUrl('sunrise', '/inventory/movements'))->assertOk()->assertSeeHtml('data-movement-filter')->assertSeeHtml('data-reverse-form');
    get(tenantUrl('sunrise', '/inventory/movements/data'))->assertOk()->assertJsonPath('recordsFiltered', 3);
    get(tenantUrl('sunrise', '/inventory/movements/data?item='.$sugar->id))->assertOk()->assertJsonPath('recordsFiltered', 1);
    get(tenantUrl('sunrise', '/inventory/movements/data?type=issue'))->assertOk()->assertJsonPath('recordsFiltered', 1);
    get(tenantUrl('sunrise', '/inventory/movements/data?from=2099-01-01'))->assertOk()->assertJsonPath('recordsFiltered', 0);

    $issue = booking(fn (): StockMovement => StockMovement::query()->where('movement_type', 'issue')->firstOrFail());
    post(tenantUrl('sunrise', "/inventory/movements/{$issue->id}/reverse"), [])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', "/inventory/movements/{$issue->id}/reverse"), ['reason' => 'Wrong store'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/inventory/movements/{$issue->id}/reverse"), ['reason' => 'Again'])->assertSessionHas('error');
    expect(booking(fn () => app(StockMovementContract::class)->balance($store->id, $rice->id)->quantity))->toBe('10.0000');
});

it('records opening balances and adjustments through the screens', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $store = skStore('MAIN');
    $rice = skItem('Rice');

    get(tenantUrl('sunrise', '/inventory/openings/new'))->assertOk()->assertSeeHtml('data-opening-form');
    post(tenantUrl('sunrise', '/inventory/openings'), ['store_id' => $store->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '40', 'unit_cost' => '120']]])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/inventory/openings'), ['store_id' => $store->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '5', 'unit_cost' => '120']]])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/inventory/openings'), ['store_id' => $store->id, 'lines' => []])->assertSessionHasErrors('lines');
    post(tenantUrl('sunrise', '/inventory/openings'), ['store_id' => 999999, 'lines' => [['item_id' => $rice->id, 'quantity' => '5', 'unit_cost' => '1']]])->assertSessionHasErrors('store_id');

    get(tenantUrl('sunrise', '/inventory/adjustments/new'))->assertOk()->assertSeeHtml('data-adjustment-form');
    post(tenantUrl('sunrise', '/inventory/adjustments'), ['store_id' => $store->id, 'item_id' => $rice->id, 'direction' => 'loss', 'quantity' => '3', 'reason' => 'damage'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/inventory/adjustments'), ['store_id' => $store->id, 'item_id' => $rice->id, 'direction' => 'loss', 'quantity' => '100', 'reason' => 'theft'])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/inventory/adjustments'), ['store_id' => $store->id, 'item_id' => $rice->id, 'direction' => 'loss', 'quantity' => '1', 'reason' => 'other'])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/inventory/adjustments'), ['store_id' => $store->id, 'item_id' => $rice->id, 'direction' => 'sideways', 'quantity' => '1', 'reason' => 'damage'])->assertSessionHasErrors('direction');

    expect(booking(fn () => app(StockMovementContract::class)->balance($store->id, $rice->id)->quantity))->toBe('37.0000');
});

it('dispatches, receives and calls back transfers through the screens', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $main = skStore('MAIN');
    $kitchen = skStore('KITCHEN');
    $rice = skItem('Rice');
    skReceive($main, $rice, '100', '110');

    get(tenantUrl('sunrise', '/inventory/transfers/new'))->assertOk()->assertSeeHtml('data-transfer-form');
    post(tenantUrl('sunrise', '/inventory/transfers'), ['from_store_id' => $main->id, 'to_store_id' => $main->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '5']]])->assertSessionHasErrors('to_store_id');
    post(tenantUrl('sunrise', '/inventory/transfers'), ['from_store_id' => $main->id, 'to_store_id' => $kitchen->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '500']]])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/inventory/transfers'), ['from_store_id' => $main->id, 'to_store_id' => $kitchen->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '30']], 'notes' => 'Weekly'])->assertSessionHas('success');
    $transfer = booking(fn (): StockTransfer => StockTransfer::query()->sole());

    get(tenantUrl('sunrise', '/inventory/transfers'))->assertOk()->assertSeeHtml('data-transfer="'.$transfer->transfer_no.'"');
    get(tenantUrl('sunrise', "/inventory/transfers/{$transfer->id}"))->assertOk()->assertSeeHtml('data-receive')->assertSeeHtml('data-cancel')->assertSee('In transit');
    post(tenantUrl('sunrise', "/inventory/transfers/{$transfer->id}/receive"))->assertSessionHas('success');
    expect(booking(fn (): TransferStatus => $transfer->fresh()->status))->toBe(TransferStatus::Received);
    get(tenantUrl('sunrise', "/inventory/transfers/{$transfer->id}"))->assertOk()->assertDontSeeHtml('data-receive');
    post(tenantUrl('sunrise', "/inventory/transfers/{$transfer->id}/cancel"))->assertSessionHas('error');

    post(tenantUrl('sunrise', '/inventory/transfers'), ['from_store_id' => $main->id, 'to_store_id' => $kitchen->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '10']]]);
    $second = booking(fn (): StockTransfer => StockTransfer::query()->latest('id')->firstOrFail());
    post(tenantUrl('sunrise', "/inventory/transfers/{$second->id}/cancel"))->assertSessionHas('success');
    expect(booking(fn () => app(StockMovementContract::class)->balance($main->id, $rice->id)->quantity))->toBe('70.0000');
});

it('lets viewers look but not change stock, and keeps others out', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $store = skStore('MAIN');
    $rice = skItem('Rice');
    skReceive($store, $rice, '10', '100');

    staffUser(DefaultRole::Chef);
    get(tenantUrl('sunrise', '/inventory/stock?store='.$store->id))->assertOk()->assertDontSeeHtml('data-adjust');
    get(tenantUrl('sunrise', '/inventory/movements'))->assertOk()->assertDontSeeHtml('data-reverse-form');
    get(tenantUrl('sunrise', '/inventory/transfers'))->assertOk()->assertDontSeeHtml('data-new-transfer');
    get(tenantUrl('sunrise', '/inventory/adjustments/new'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/openings/new'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/transfers/new'))->assertForbidden();
    post(tenantUrl('sunrise', '/inventory/adjustments'), ['store_id' => $store->id, 'item_id' => $rice->id, 'direction' => 'loss', 'quantity' => '1', 'reason' => 'damage'])->assertForbidden();
    post(tenantUrl('sunrise', '/inventory/transfers'), ['from_store_id' => $store->id, 'to_store_id' => $store->id, 'lines' => []])->assertForbidden();

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/inventory/stock'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/movements/data'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/transfers'))->assertForbidden();
});

it('shows what an item holds in each store on its page', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $store = skStore('MAIN');
    $rice = skItem('Rice');
    skReceive($store, $rice, '12.5', '100');

    get(tenantUrl('sunrise', "/inventory/items/{$rice->id}/edit"))->assertOk()->assertSeeHtml('data-on-hand')->assertSee('Main store')->assertSee('12.5');
});
