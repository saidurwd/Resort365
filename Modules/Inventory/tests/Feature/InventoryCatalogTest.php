<?php

/*
| Items, units and stores (Step 5.2): the actions and the contracts other modules use.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Actions\ImportItems;
use Modules\Inventory\Actions\SaveCategory;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SaveUnit;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Inventory\DTOs\ItemSummary;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Exceptions\UnitNotConvertible;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Property\Models\Department;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

function ivUnit(string $code): Unit
{
    return booking(fn (): Unit => Unit::query()->where('code', $code)->firstOrFail());
}

function ivCategory(string $name = 'Food'): ItemCategory
{
    return booking(fn (): ItemCategory => ItemCategory::query()->firstOrCreate(['name' => $name], ['sort_order' => 0, 'is_active' => true]));
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  list<array{unit_id: int, factor: string}>  $conversions
 * @param  list<array{store_id: int, par_level: string|null, reorder_level: string|null}>  $levels
 */
function ivItem(array $overrides = [], array $conversions = [], array $levels = [], ?Item $item = null): Item
{
    return booking(fn (): Item => app(SaveItem::class)->handle($item, [
        'item_category_id' => ivCategory()->id, 'name' => 'Basmati rice', 'base_unit_id' => ivUnit('kg')->id, 'reorder_level' => '10', 'reorder_qty' => '50', ...$overrides,
    ], $conversions, $levels));
}

function ivStore(string $code = 'MAIN', string $kind = 'main'): Store
{
    return booking(fn (): Store => app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => $code, 'name' => ucfirst(strtolower($code)).' store', 'kind' => $kind]));
}

it('seeds the standard units once, with their sizes', function (): void {
    expect(booking(fn (): int => app(SeedStandardUnits::class)->handle()))->toBe(0)
        ->and(ivUnit('kg')->factor)->toBe('1000.000000')->and(booking(fn (): string => ivUnit('kg')->baseUnit->code))->toBe('g')->and(booking(fn () => ivUnit('box')->baseUnit))->toBeNull()
        ->and(booking(fn (): int => Unit::query()->count()))->toBe(count(SeedStandardUnits::UNITS));
});

it('saves units with the rules of kinds and sizes', function (): void {
    $save = fn (array $data, ?Unit $unit = null) => booking(fn () => app(SaveUnit::class)->handle($unit, $data));

    $quart = $save(['code' => 'Qt', 'name' => 'Quart', 'kind' => 'volume', 'base_unit_id' => ivUnit('ml')->id, 'factor' => '946.353']);
    expect($quart->code)->toBe('qt')->and($quart->factor)->toBe('946.353000');

    expect(fn () => $save(['code' => 'x1', 'name' => 'X', 'kind' => 'volume', 'base_unit_id' => ivUnit('g')->id, 'factor' => '2']))->toThrow(InventoryRuleViolated::class)
        ->and(fn () => $save(['code' => 'x2', 'name' => 'X', 'kind' => 'volume', 'base_unit_id' => ivUnit('l')->id, 'factor' => '2']))->toThrow(InventoryRuleViolated::class)
        ->and(fn () => $save(['code' => 'x3', 'name' => 'X', 'kind' => 'pack', 'base_unit_id' => ivUnit('pcs')->id, 'factor' => '2']))->toThrow(InventoryRuleViolated::class)
        ->and(fn () => $save(['code' => 'x4', 'name' => 'X', 'kind' => 'weight', 'base_unit_id' => ivUnit('g')->id, 'factor' => '0']))->toThrow(InventoryRuleViolated::class);

    ivItem();
    expect(fn () => $save(['code' => 'kg', 'name' => 'Kilogram', 'kind' => 'volume'], ivUnit('kg')))->toThrow(InventoryRuleViolated::class, 'in use');
});

it('keeps categories out of themselves', function (): void {
    $food = ivCategory('Food');
    $dairy = booking(fn (): ItemCategory => app(SaveCategory::class)->handle(null, ['name' => 'Dairy', 'parent_id' => $food->id]));
    $cheese = booking(fn (): ItemCategory => app(SaveCategory::class)->handle(null, ['name' => 'Cheese', 'parent_id' => $dairy->id]));

    expect(booking(fn (): string => $cheese->fresh()->path()))->toBe('Food › Dairy › Cheese');
    expect(fn () => booking(fn () => app(SaveCategory::class)->handle($food, ['name' => 'Food', 'parent_id' => $cheese->id])))->toThrow(InventoryRuleViolated::class, 'inside itself')
        ->and(fn () => booking(fn () => app(SaveCategory::class)->handle($food, ['name' => 'Food', 'parent_id' => $food->id])))->toThrow(InventoryRuleViolated::class);
});

it('numbers items automatically and keeps SKUs and barcodes unique', function (): void {
    $first = ivItem(['name' => 'Rice', 'barcode' => '8801']);
    $second = ivItem(['name' => 'Sugar']);

    expect($first->sku)->toBe('ITM-00001')->and($second->sku)->toBe('ITM-00002');
    expect(fn () => ivItem(['name' => 'Again', 'sku' => 'itm-00001']))->toThrow(InventoryRuleViolated::class, 'already used')
        ->and(fn () => ivItem(['name' => 'Again', 'barcode' => '8801']))->toThrow(InventoryRuleViolated::class, 'barcode');

    $renamed = ivItem(['name' => 'Rice (basmati)', 'barcode' => '8801'], item: $first);
    expect($renamed->sku)->toBe('ITM-00001')->and($renamed->name)->toBe('Rice (basmati)');
});

it('needs a conversion for a purchase unit that is not of the base unit\'s kind', function (): void {
    expect(fn () => ivItem(['purchase_unit_id' => ivUnit('sack')->id]))->toThrow(InventoryRuleViolated::class, 'needs a conversion');

    $item = ivItem(['purchase_unit_id' => ivUnit('sack')->id], [['unit_id' => ivUnit('sack')->id, 'factor' => '25']]);
    expect($item->conversions)->toHaveCount(1)->and($item->conversions[0]->factor)->toBe('25.000000');

    expect(ivItem(['name' => 'Flour', 'purchase_unit_id' => ivUnit('g')->id])->purchase_unit_id)->toBe(ivUnit('g')->id)
        ->and(fn () => ivItem(['name' => 'Salt'], [['unit_id' => ivUnit('kg')->id, 'factor' => '0']]))->toThrow(InventoryRuleViolated::class)
        ->and(fn () => ivItem(['name' => 'Salt'], [['unit_id' => ivUnit('kg')->id, 'factor' => '1']]))->toThrow(InventoryRuleViolated::class, 'different unit')
        ->and(fn () => ivItem(['name' => 'Salt'], [['unit_id' => ivUnit('sack')->id, 'factor' => '5'], ['unit_id' => ivUnit('sack')->id, 'factor' => '6']]))->toThrow(InventoryRuleViolated::class);
});

it('keeps levels per store, one set each, and drops empty ones', function (): void {
    $main = ivStore('MAIN');
    $kitchen = ivStore('KITCHEN', 'kitchen');
    $item = ivItem(levels: [['store_id' => $main->id, 'par_level' => '100', 'reorder_level' => '20'], ['store_id' => $kitchen->id, 'par_level' => null, 'reorder_level' => null]]);

    expect($item->levels)->toHaveCount(1)->and($item->levels[0]->par_level)->toBe('100.0000');
    expect(fn () => ivItem(['name' => 'Oil'], levels: [['store_id' => $main->id, 'par_level' => '1', 'reorder_level' => null], ['store_id' => $main->id, 'par_level' => '2', 'reorder_level' => null]]))->toThrow(InventoryRuleViolated::class);
});

it('turns quantities into base units through the catalogue contract', function (): void {
    $rice = ivItem(['name' => 'Rice'], [['unit_id' => ivUnit('sack')->id, 'factor' => '25']]);
    $catalog = fn (): ItemCatalog => app(ItemCatalog::class);

    expect(booking(fn (): string => $catalog()->toBaseQuantity($rice->id, '4', ivUnit('kg')->id)))->toBe('4')
        ->and(booking(fn (): string => $catalog()->toBaseQuantity($rice->id, '2', ivUnit('sack')->id)))->toBe('50')
        ->and(booking(fn (): string => $catalog()->toBaseQuantity($rice->id, '2500', ivUnit('g')->id)))->toBe('2.5')
        ->and(fn () => booking(fn () => $catalog()->toBaseQuantity($rice->id, '1', ivUnit('l')->id)))->toThrow(UnitNotConvertible::class)
        ->and(fn () => booking(fn () => $catalog()->toBaseQuantity($rice->id, '1', ivUnit('carton')->id)))->toThrow(UnitNotConvertible::class, 'no conversion');
});

it('finds and searches items through the catalogue contract', function (): void {
    $rice = ivItem(['name' => 'Basmati rice', 'barcode' => '8801']);
    ivItem(['name' => 'Brown rice']);
    ivItem(['name' => 'Retired', 'is_active' => false]);
    $catalog = app(ItemCatalog::class);

    $summary = booking(fn () => $catalog->find($rice->id));
    expect($summary?->sku)->toBe('ITM-00001')->and($summary?->category)->toBe('Food')->and($summary?->baseUnit)->toBe('kg')
        ->and(booking(fn (): array => array_map(fn (ItemSummary $item): string => $item->name, $catalog->search('rice'))))->toBe(['Basmati rice', 'Brown rice'])
        ->and(booking(fn (): int => count($catalog->search('8801'))))->toBe(1)
        ->and(booking(fn (): int => count($catalog->search('itm-0000'))))->toBe(2)
        ->and(booking(fn () => $catalog->find(999999)))->toBeNull();
});

it('imports items, creating categories and updating by SKU, all or nothing', function (): void {
    $csv = "sku,name,category,base_unit,reorder_level,standard_cost\nRICE-5,Basmati rice,Food > Dry goods,kg,50,120\nTWL-B,Bath towel,Housekeeping > Linen,pcs,100,450\n";
    $result = booking(fn (): array => app(ImportItems::class)->handle($csv));

    expect($result)->toBe(['created' => 2, 'updated' => 0])
        ->and(booking(fn (): string => Item::query()->where('sku', 'RICE-5')->firstOrFail()->category->path()))->toBe('Food › Dry goods')
        ->and(booking(fn (): int => ItemCategory::query()->count()))->toBe(4);

    $again = booking(fn (): array => app(ImportItems::class)->handle("sku,name,category,base_unit,standard_cost\nrice-5,Basmati rice premium,Food > Dry goods,kg,135\nNEW-1,New item,Food > Dry goods,kg,\n"));
    expect($again)->toBe(['created' => 1, 'updated' => 1])->and(booking(fn () => Item::query()->where('sku', 'RICE-5')->firstOrFail()->only(['name', 'standard_cost'])))->toBe(['name' => 'Basmati rice premium', 'standard_cost' => '135.0000'])
        ->and(booking(fn (): int => ItemCategory::query()->count()))->toBe(4);

    expect(fn () => booking(fn () => app(ImportItems::class)->handle("sku,name,category,base_unit\nOK-1,Fine,Food,kg\nBAD-1,Bad,Food,furlong\n")))->toThrow(InventoryRuleViolated::class, 'furlong')
        ->and(booking(fn (): bool => Item::query()->where('sku', 'OK-1')->exists()))->toBeFalse();
});

it('refuses an import whose barcode belongs to another item', function (): void {
    ivItem(['name' => 'Rice', 'barcode' => '8801']);

    expect(fn () => booking(fn () => app(ImportItems::class)->handle("sku,name,category,base_unit,barcode\nZ-1,Other,Food,kg,8801\n")))->toThrow(InventoryRuleViolated::class, 'barcode');
});

it('keeps store codes unique per property and ties stores to departments', function (): void {
    $department = booking(fn (): Department => Department::factory()->create());
    $store = booking(fn (): Store => app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => 'bar', 'name' => 'Bar', 'kind' => 'bar', 'department_id' => $department->id]));

    expect($store->code)->toBe('BAR')->and($store->department_id)->toBe($department->id);
    expect(fn () => ivStore('BAR'))->toThrow(InventoryRuleViolated::class, 'already has a store')
        ->and(fn () => booking(fn () => app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => 'X', 'name' => 'X', 'kind' => 'main', 'department_id' => 999999])))->toThrow(InventoryRuleViolated::class);
    booking(fn () => app(SaveStore::class)->handle(bookingIds()['property'], $store, ['code' => 'BAR', 'name' => 'Pool bar', 'kind' => 'bar', 'is_active' => false]));

    $directory = app(StoreDirectory::class);
    expect(booking(fn (): int => count($directory->forProperty(bookingIds()['property']))))->toBe(0)
        ->and(booking(fn (): int => count($directory->forProperty(bookingIds()['property'], false))))->toBe(1)
        ->and(booking(fn () => $directory->find($store->id)?->name))->toBe('Pool bar');
});
