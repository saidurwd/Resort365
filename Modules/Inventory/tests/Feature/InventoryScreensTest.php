<?php

/*
| Inventory screens (Step 5.2): items with conversions and levels, categories, units, stores, the CSV import, and who
| may do what; plus the restaurant outlet's store.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Inventory\Actions\SaveCategory;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Property\Models\Property;
use Modules\Restaurant\Models\Outlet;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

function scUnit(string $code): int
{
    return booking(fn (): int => Unit::query()->where('code', $code)->value('id'));
}

function scCategory(string $name, ?int $parent = null): int
{
    return booking(fn (): int => app(SaveCategory::class)->handle(null, ['name' => $name, 'parent_id' => $parent])->id);
}

function scStore(string $code = 'KITCHEN'): int
{
    return booking(fn (): int => app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => $code, 'name' => ucfirst(strtolower($code)).' store', 'kind' => 'kitchen'])->id);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function scItemForm(array $overrides = []): array
{
    return ['item_category_id' => scCategory('Food'), 'name' => 'Basmati rice', 'base_unit_id' => scUnit('kg'), 'reorder_level' => '10', 'reorder_qty' => '50', 'is_stockable' => '1', 'is_active' => '1', ...$overrides];
}

it('adds an item with its own conversions and levels, and changes it', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $store = scStore();

    post(tenantUrl('sunrise', '/inventory/items'), scItemForm([
        'purchase_unit_id' => scUnit('sack'), 'conversions' => [['unit_id' => scUnit('sack'), 'factor' => '25']], 'levels' => [['store_id' => $store, 'par_level' => '200', 'reorder_level' => '40']], 'barcode' => '8801',
    ]))->assertSessionHasNoErrors()->assertRedirect();
    $item = booking(fn (): Item => Item::query()->with(['conversions', 'levels'])->sole());

    expect($item->sku)->toBe('ITM-00001')->and($item->conversions->sole()->factor)->toBe('25.000000')->and($item->levels->sole()->par_level)->toBe('200.0000');
    get(tenantUrl('sunrise', "/inventory/items/{$item->id}/edit"))->assertOk()->assertSeeHtml('data-item-form')->assertSeeHtml('data-add-conversion')->assertSee('ITM-00001');

    put(tenantUrl('sunrise', "/inventory/items/{$item->id}"), scItemForm(['name' => 'Basmati rice 5 kg', 'sku' => 'ITM-00001', 'item_category_id' => $item->item_category_id, 'conversions' => [], 'levels' => []]))->assertSessionHas('success');
    expect(booking(fn () => $item->fresh()->name))->toBe('Basmati rice 5 kg')->and(booking(fn (): int => $item->fresh()->conversions()->count()))->toBe(0);
});

it('validates items: tenant-scoped ids, factors and rule violations', function (): void {
    staffUser(DefaultRole::StoreKeeper);

    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['name' => '']))->assertSessionHasErrors('name');
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['base_unit_id' => 999999]))->assertSessionHasErrors('base_unit_id');
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['conversions' => [['unit_id' => scUnit('box'), 'factor' => '0']]]))->assertSessionHasErrors('conversions.0.factor');
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['purchase_unit_id' => scUnit('box')]))->assertSessionHas('error');
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['sku' => 'A-1']))->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['sku' => 'a-1', 'name' => 'Other']))->assertSessionHas('error');

    expect(booking(fn (): int => Item::query()->count()))->toBe(1);
});

it('lists items with filters, a category including its subcategories', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $food = scCategory('Food');
    $dairy = scCategory('Dairy', $food);
    $linen = scCategory('Linen');
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['item_category_id' => $food, 'name' => 'Rice']));
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['item_category_id' => $dairy, 'name' => 'Milk', 'base_unit_id' => scUnit('l')]));
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['item_category_id' => $linen, 'name' => 'Towel', 'base_unit_id' => scUnit('pcs'), 'is_active' => '0']));

    get(tenantUrl('sunrise', '/inventory/items'))->assertOk()->assertSeeHtml('data-new-item')->assertSeeHtml('data-import');
    get(tenantUrl('sunrise', '/inventory/items/data'))->assertOk()->assertJsonPath('recordsFiltered', 3);
    get(tenantUrl('sunrise', '/inventory/items/data?category='.$food))->assertOk()->assertJsonPath('recordsFiltered', 2);
    get(tenantUrl('sunrise', '/inventory/items/data?active=0'))->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.name', 'Towel');
    get(tenantUrl('sunrise', '/inventory/items/data?stockable=0'))->assertOk()->assertJsonPath('recordsFiltered', 0);
});

it('manages categories, units and stores on their screens', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $food = scCategory('Food');

    post(tenantUrl('sunrise', '/inventory/categories'), ['name' => 'Dairy', 'parent_id' => $food, 'sort_order' => 1, 'is_active' => '1'])->assertSessionHas('success');
    $dairy = booking(fn (): ItemCategory => ItemCategory::query()->where('name', 'Dairy')->sole());
    put(tenantUrl('sunrise', "/inventory/categories/{$food}"), ['name' => 'Food', 'parent_id' => $dairy->id])->assertSessionHas('error');
    get(tenantUrl('sunrise', '/inventory/categories'))->assertOk()->assertSee('Food › Dairy')->assertSeeHtml('data-category="'.$dairy->id.'"');

    post(tenantUrl('sunrise', '/inventory/units'), ['code' => 'Qt', 'name' => 'Quart', 'kind' => 'volume', 'base_unit_id' => scUnit('ml'), 'factor' => '946.353', 'is_active' => '1'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/inventory/units'), ['code' => 'qt', 'name' => 'Again', 'kind' => 'volume'])->assertSessionHasErrors('code');
    post(tenantUrl('sunrise', '/inventory/units'), ['code' => 'bad', 'name' => 'Bad', 'kind' => 'weight', 'base_unit_id' => scUnit('ml'), 'factor' => '2'])->assertSessionHas('error');
    get(tenantUrl('sunrise', '/inventory/units'))->assertOk()->assertSeeHtml('data-unit="kg"')->assertSeeHtml('data-unit="qt"')->assertSee('1000 g');

    post(tenantUrl('sunrise', '/inventory/stores'), ['code' => 'bar', 'name' => 'Bar store', 'kind' => 'bar', 'is_active' => '1'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/inventory/stores'), ['code' => 'BAR', 'name' => 'Again', 'kind' => 'bar'])->assertSessionHas('error');
    get(tenantUrl('sunrise', '/inventory/stores'))->assertOk()->assertSeeHtml('data-store="BAR"');
});

it('imports items from a file through the screen, and refuses a bad file as a whole', function (): void {
    staffUser(DefaultRole::StoreKeeper);

    get(tenantUrl('sunrise', '/inventory/import'))->assertOk()->assertSeeHtml('data-import-form');
    post(tenantUrl('sunrise', '/inventory/import'), ['file' => UploadedFile::fake()->createWithContent('items.csv', "sku,name,category,base_unit\nR1,Rice,Food > Dry goods,kg\nT1,Towel,Housekeeping > Linen,pcs\n")])->assertSessionHas('success');
    expect(booking(fn (): int => Item::query()->count()))->toBe(2);

    post(tenantUrl('sunrise', '/inventory/import'), ['file' => UploadedFile::fake()->createWithContent('bad.csv', "sku,name,category,base_unit\nR2,Rice,Food,furlong\n")])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/inventory/import'), [])->assertSessionHasErrors('file');
    expect(booking(fn (): int => Item::query()->count()))->toBe(2);
});

it('lets viewers look but not change, and keeps others out', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm());
    $item = booking(fn (): Item => Item::query()->sole());

    staffUser(DefaultRole::Chef);
    get(tenantUrl('sunrise', '/inventory/items'))->assertOk()->assertDontSeeHtml('data-new-item');
    get(tenantUrl('sunrise', '/inventory/units'))->assertOk();
    get(tenantUrl('sunrise', '/inventory/stores'))->assertOk();
    get(tenantUrl('sunrise', '/inventory/items/new'))->assertForbidden();
    get(tenantUrl('sunrise', "/inventory/items/{$item->id}/edit"))->assertForbidden();
    post(tenantUrl('sunrise', '/inventory/items'), scItemForm(['name' => 'X']))->assertForbidden();
    post(tenantUrl('sunrise', '/inventory/categories'), ['name' => 'X'])->assertForbidden();
    post(tenantUrl('sunrise', '/inventory/stores'), ['code' => 'X', 'name' => 'X', 'kind' => 'main'])->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/import'))->assertForbidden();

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/inventory/items'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/items/data'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/stores'))->assertForbidden();
});

it('links a restaurant outlet to a store of its property', function (): void {
    staffUser(DefaultRole::FnbManager);
    $kitchen = scStore('KITCHEN');
    $other = booking(fn (): int => Store::factory()->create(['property_id' => Property::factory()->create(['code' => 'DHK'])->id, 'code' => 'ZZ'])->id);
    $payload = ['code' => 'MR', 'name' => 'Main Restaurant', 'type' => 'restaurant', 'bill_prefix' => 'MR', 'prices_include_tax' => '0', 'is_active' => '1', 'opening_hours' => ['mon' => ['open' => '07:00', 'close' => '23:00']]];

    post(tenantUrl('sunrise', '/restaurant/outlets'), [...$payload, 'store_id' => $kitchen])->assertSessionHasNoErrors();
    $outlet = booking(fn (): Outlet => Outlet::query()->firstOrFail());
    expect($outlet->store_id)->toBe($kitchen);

    post(tenantUrl('sunrise', '/restaurant/outlets'), [...$payload, 'code' => 'PB', 'name' => 'Pool Bar', 'bill_prefix' => 'PB', 'store_id' => 999999])->assertSessionHasErrors('store_id');
    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/edit"))->assertOk()->assertSee('Kitchen store');
    post(tenantUrl('sunrise', '/restaurant/outlets'), [...$payload, 'code' => 'RS', 'name' => 'Room Service', 'bill_prefix' => 'RS', 'store_id' => $other])->assertSessionHasErrors('store_id');
});
