<?php

/*
| Inventory reports (Step 5.8): stock valuation, consumption by department, slow-moving stock, and theoretical against
| actual use. "Done when": after a stock count, the theoretical vs actual report shows the variance per ingredient.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Actions\CompleteStockCount;
use Modules\Inventory\Actions\DispatchTransfer;
use Modules\Inventory\Actions\IssueStock;
use Modules\Inventory\Actions\SaveCountEntries;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Actions\StartStockCount;
use Modules\Inventory\Contracts\StockFacts;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\Reports\InventoryReports;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;

use function Pest\Laravel\get;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

function irToday(): string
{
    return booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString());
}

function irStore(string $code = 'KIT'): Store
{
    return booking(fn (): Store => Store::query()->where('code', $code)->first() ?? app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => $code, 'name' => $code.' store', 'kind' => 'kitchen']));
}

function irItem(string $name): Item
{
    return booking(function () use ($name): Item {
        $category = ItemCategory::query()->firstOrCreate(['name' => 'Food'], ['sort_order' => 0, 'is_active' => true, 'account_class' => 'food']);

        return Item::query()->where('name', $name)->first() ?? app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => $name, 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id')]);
    });
}

/**
 * @param  array<string, mixed>  $extra
 */
function irMove(Store $store, Item $item, MovementType $type, string $quantity, ?string $cost = null, ?string $date = null, array $extra = []): void
{
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement($type, $store->id, $item->id, $quantity, $cost, ...$extra, date: $date)));
}

it('values stock per store and item at the average cost, today and on a past day', function (): void {
    $store = irStore();
    $rice = irItem('Rice');
    $sugar = irItem('Sugar');
    irMove($store, $rice, MovementType::Receipt, '10', '100', '2026-01-05');
    irMove($store, $rice, MovementType::Issue, '4', null, '2026-01-20');
    irMove($store, $sugar, MovementType::Receipt, '30', '90', '2026-01-15');
    $reports = booking(fn (): InventoryReports => app(InventoryReports::class));
    $property = bookingIds()['property'];

    $now = booking(fn (): array => $reports->valuation($property, null, null));
    expect($now['rows'])->toBe([['KIT store', 'Rice ('.$rice->sku.')', 'food', '6 kg', '100.0000', '600.00'], ['KIT store', 'Sugar ('.$sugar->sku.')', 'food', '30 kg', '90.0000', '2,700.00']])
        ->and($now['totals'])->toBe(['Total', '', '', '', '', '3,300.00'])->and($now['summary'][0])->toBe(['label' => 'Total value', 'value' => '3,300.00']);

    $past = booking(fn (): array => $reports->valuation($property, '2026-01-10', null));
    expect($past['rows'])->toHaveCount(1)->and($past['rows'][0][5])->toBe('1,000.00')->and($past['totals'][5])->toBe('1,000.00');
});

it('lists issues to departments with their cost', function (): void {
    $store = irStore();
    $rice = irItem('Rice');
    irMove($store, $rice, MovementType::Receipt, '50', '100');
    $kitchen = booking(fn (): Department => Department::factory()->create(['name' => 'Kitchen']));
    booking(fn () => app(IssueStock::class)->handle($store->id, $kitchen->id, [['item_id' => $rice->id, 'quantity' => '5']], 'Canteen'));
    booking(fn () => app(IssueStock::class)->handle($store->id, $kitchen->id, [['item_id' => $rice->id, 'quantity' => '3']], 'Canteen'));
    $today = irToday();

    $report = booking(fn (): array => app(InventoryReports::class)->departments(bookingIds()['property'], $today, $today, null));

    expect($report['rows'])->toBe([['Kitchen', 'Rice', '8 kg', '800.00']])->and($report['totals'][3])->toBe('800.00');
});

it('finds stock that has not moved in a while', function (): void {
    $store = irStore();
    $rice = irItem('Rice');
    $sugar = irItem('Sugar');
    $salt = irItem('Salt');
    $today = irToday();
    irMove($store, $rice, MovementType::Receipt, '10', '100', CarbonImmutable::parse($today)->subDays(200)->toDateString());
    irMove($store, $sugar, MovementType::Receipt, '10', '100', CarbonImmutable::parse($today)->subDays(200)->toDateString());
    irMove($store, $sugar, MovementType::Issue, '1', null, CarbonImmutable::parse($today)->subDays(100)->toDateString());
    irMove($store, $salt, MovementType::Receipt, '10', '50', CarbonImmutable::parse($today)->subDays(200)->toDateString());
    irMove($store, $salt, MovementType::Issue, '1', null, CarbonImmutable::parse($today)->subDays(5)->toDateString());

    $report = booking(fn (): array => app(InventoryReports::class)->slowMoving(bookingIds()['property'], 60, null, $today));

    expect(array_column($report['rows'], 1))->toBe(['Rice ('.$rice->sku.')', 'Sugar ('.$sugar->sku.')'])->and($report['rows'][0][4])->toBe('never')->and($report['rows'][1][5])->toBe('100')->and($report['totals'][3])->toBe('1,900.00');
});

it('shows the variance per ingredient after a stock count (done when)', function (): void {
    $store = irStore();
    $rice = irItem('Rice');
    $flour = irItem('Flour');
    irMove($store, $rice, MovementType::Receipt, '100', '100');
    irMove($store, $rice, MovementType::Consumption, '30', null, null, ['sourceType' => 'pos_bill', 'sourceId' => 1, 'sourceEvent' => 'consumed']);
    irMove($store, $rice, MovementType::Wastage, '5');
    irMove($store, $rice, MovementType::Issue, '10');
    irMove($store, $flour, MovementType::Receipt, '20', '60');
    $count = booking(fn () => app(StartStockCount::class)->handle($store->id));
    booking(fn () => app(SaveCountEntries::class)->handle($count, [$rice->id => '52', $flour->id => '20']));
    booking(fn () => app(CompleteStockCount::class)->handle($count->fresh() ?? $count));
    $today = irToday();

    $report = booking(fn (): array => app(InventoryReports::class)->theoreticalVsActual(bookingIds()['property'], $today, $today, $store->id));
    $row = collect($report['rows'])->first(fn (array $cells): bool => str_starts_with($cells[0], 'Rice'));

    // Opening 0 + received 100 − sales use 30 − wastage 5 − issued 10 + count variance (−3) = closing 52.
    expect($row)->toBe(['Rice ('.$rice->sku.')', 'kg', '0', '100', '30', '5', '10', '-3', '52', '-300.00', '-10.0%'])
        ->and(collect($report['rows'])->first(fn (array $cells): bool => str_starts_with($cells[0], 'Flour'))[7])->toBe('0')
        ->and($report['totals'][9])->toBe('-300.00')->and($report['summary'][0]['value'])->toBe('-300.00');
});

it('opens a period from the closing of the one before', function (): void {
    $store = irStore();
    $rice = irItem('Rice');
    irMove($store, $rice, MovementType::Receipt, '100', '100', '2026-01-05');
    irMove($store, $rice, MovementType::Consumption, '20', null, '2026-01-10', ['sourceEvent' => 'consumed']);
    irMove($store, $rice, MovementType::Consumption, '15', null, '2026-02-10', ['sourceEvent' => 'consumed']);

    $report = booking(fn (): array => app(InventoryReports::class)->theoreticalVsActual(bookingIds()['property'], '2026-02-01', '2026-02-28', $store->id));

    expect($report['rows'][0])->toBe(['Rice ('.$rice->sku.')', 'kg', '80', '0', '15', '0', '0', '0', '65', '0.00', '0.0%'])
        ->and(booking(fn (): array => app(InventoryReports::class)->theoreticalVsActual(bookingIds()['property'], '2026-02-01', '2026-02-28', null)['columns']))->toBe([]);
});

it('counts stock in transit between stores apart from stock on the shelves', function (): void {
    $kitchen = irStore('KIT');
    $bar = irStore('BAR');
    $rice = irItem('Rice');
    irMove($kitchen, $rice, MovementType::Receipt, '100', '100');
    booking(fn () => app(DispatchTransfer::class)->handle($kitchen->id, $bar->id, [['item_id' => $rice->id, 'quantity' => '40']]));

    $valuation = booking(fn (): array => app(StockFacts::class)->valuation(bookingIds()['property']));

    expect($valuation)->toBe(['stock' => ['food' => '6000.00'], 'in_transit' => ['food' => '4000.00']]);
});

it('shows the reports on screen and as CSV, to those who may see them', function (): void {
    $store = irStore();
    $rice = irItem('Rice');
    irMove($store, $rice, MovementType::Receipt, '10', '100');
    staffUser(DefaultRole::StoreKeeper);

    get(tenantUrl('sunrise', '/inventory/reports/valuation'))->assertOk()->assertSeeHtml('data-report-table="valuation"')->assertSee('1,000.00')->assertSeeHtml('data-report-tab="usage"');
    get(tenantUrl('sunrise', '/inventory/reports/valuation?store='.$store->id.'&as_of=2000-01-01'))->assertOk()->assertDontSee('1,000.00');
    get(tenantUrl('sunrise', '/inventory/reports/departments'))->assertOk()->assertSeeHtml('data-report-table="departments"');
    get(tenantUrl('sunrise', '/inventory/reports/slow-moving?days=30'))->assertOk();
    get(tenantUrl('sunrise', '/inventory/reports/usage'))->assertOk()->assertSee('Used in sales');
    get(tenantUrl('sunrise', '/inventory/reports/valuation?export=csv'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertDownload();
    get(tenantUrl('sunrise', '/inventory/reports/valuation?store=999999'))->assertSessionHasErrors('store');
});

it('keeps the inventory reports to the people who hold the permission', function (): void {
    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/inventory/reports/valuation'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/reports/usage'))->assertForbidden();

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/inventory/reports/valuation'))->assertOk();
});
