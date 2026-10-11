<?php

/*
| Accounting (Step 5.4), stock postings. "Done when": a requisition is issued and the department's cost shows in the
| ledger; count losses go to inventory shrinkage and gains reverse it.
*/
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\SaveAccountMappings;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\MappingCatalog;
use Modules\Accounting\Services\PostingService;
use Modules\Inventory\Actions\CompleteStockCount;
use Modules\Inventory\Actions\IssueStock;
use Modules\Inventory\Actions\SaveCountEntries;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Actions\StartStockCount;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(function (): void {
        app(SeedStandardUnits::class)->handle();
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(CarbonImmutable::parse(Property::query()->where('code', 'CXB')->sole()->business_date->toDateString())->startOfMonth()->subMonths(2)->toDateString());
    });
});

function spStore(): Store
{
    return booking(fn (): Store => app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => 'KIT', 'name' => 'Kitchen store', 'kind' => 'kitchen']));
}

function spItem(string $name, string $class = 'food'): Item
{
    return booking(function () use ($name, $class): Item {
        $category = ItemCategory::query()->firstOrCreate(['name' => ucfirst($class)], ['sort_order' => 0, 'is_active' => true, 'account_class' => $class]);

        return app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => $name, 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id')]);
    });
}

function spStock(Store $store, Item $item, string $quantity, string $cost): void
{
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $item->id, $quantity, $cost)));
}

/**
 * The entry's lines as "system key => net debit".
 *
 * @return array<string, string>
 */
function spEntry(string $type, int $id, string $event): array
{
    return booking(function () use ($type, $id, $event): array {
        $entry = JournalEntry::query()->where('source_type', $type)->where('source_id', $id)->where('source_event', $event)->firstOrFail();
        $lines = [];

        foreach (JournalLine::query()->where('journal_entry_id', $entry->id)->with('account')->get() as $line) {
            $key = $line->account->system_key ?? $line->account->code;
            $lines[$key] = number_format((float) ($lines[$key] ?? 0) + (float) $line->debit - (float) $line->credit, 2, '.', '');
        }

        return $lines;
    });
}

it('charges an issue to the department: cost of the class in, inventory out', function (): void {
    $store = spStore();
    $rice = spItem('Rice');
    $soap = spItem('Soap', 'housekeeping');
    spStock($store, $rice, '50', '100');
    spStock($store, $soap, '100', '10');
    $department = booking(fn (): Department => Department::factory()->create());

    $issue = booking(fn () => app(IssueStock::class)->handle($store->id, $department->id, [['item_id' => $rice->id, 'quantity' => '10'], ['item_id' => $soap->id, 'quantity' => '20']], 'Canteen'));

    expect(spEntry('stock_issue', $issue->id, 'issued'))->toBe(['food_cost' => '1000.00', 'inventory_food' => '-1000.00', 'housekeeping_supplies_expense' => '200.00', 'inventory_housekeeping' => '-200.00']);

    $cost = booking(fn (): array => JournalLine::query()->whereHas('account', fn ($query) => $query->where('system_key', 'food_cost'))->pluck('department_id', 'property_id')->all());
    expect($cost)->toBe([bookingIds()['property'] => $department->id]);

    booking(fn () => app(PostingService::class)->stockIssue($issue->id));
    expect(booking(fn (): int => JournalEntry::query()->where('source_type', 'stock_issue')->count()))->toBe(1);
});

it('posts a count loss to inventory shrinkage and a gain the other way', function (): void {
    $store = spStore();
    $rice = spItem('Rice');
    $sugar = spItem('Sugar');
    spStock($store, $rice, '20', '100');
    spStock($store, $sugar, '10', '50');
    $count = booking(fn () => app(StartStockCount::class)->handle($store->id));
    booking(fn () => app(SaveCountEntries::class)->handle($count, [$rice->id => '18', $sugar->id => '12']));
    booking(fn () => app(CompleteStockCount::class)->handle($count->fresh() ?? $count));

    // Rice -2 kg (200.00 lost), sugar +2 kg (100.00 found): both food, so the inventory account nets -100.00.
    expect(spEntry('stock_count', $count->id, 'adjusted'))->toBe(['inventory_shrinkage' => '100.00', 'inventory_food' => '-100.00']);
});

it('posts a manual adjustment', function (): void {
    $store = spStore();
    $rice = spItem('Rice');
    spStock($store, $rice, '20', '100');
    $movement = booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::AdjustmentOut, $store->id, $rice->id, '3', reason: 'damage')));
    booking(fn () => app(PostingService::class)->stockAdjustment('stock_movement', $movement->id));

    expect(spEntry('stock_movement', $movement->id, 'adjusted'))->toBe(['inventory_shrinkage' => '300.00', 'inventory_food' => '-300.00']);
});

it('follows a mapped account, lists the stock keys, and back-posts what was missed', function (): void {
    $store = spStore();
    $rice = spItem('Rice');
    spStock($store, $rice, '50', '100');
    $department = booking(fn (): Department => Department::factory()->create());
    $special = booking(fn (): int => Account::query()->where('system_key', 'beverage_cost')->value('id'));
    booking(fn () => app(SaveAccountMappings::class)->handle(['stock:expense:food' => $special]));

    $issue = booking(fn () => app(IssueStock::class)->handle($store->id, $department->id, [['item_id' => $rice->id, 'quantity' => '5']], 'Canteen'));
    expect(spEntry('stock_issue', $issue->id, 'issued'))->toBe(['beverage_cost' => '500.00', 'inventory_food' => '-500.00']);

    $keys = booking(fn (): array => array_column(app(MappingCatalog::class)->keys(), 'key'));
    expect($keys)->toContain('stock:expense:food', 'stock:inventory:beverage', 'stock:shrinkage');

    // The entry is lost (as if Accounting had been off): the back-post brings it back, once.
    booking(function () use ($issue): void {
        JournalLine::query()->whereIn('journal_entry_id', JournalEntry::query()->where('source_type', 'stock_issue')->pluck('id'))->delete();
        JournalEntry::query()->where('source_type', 'stock_issue')->where('source_id', $issue->id)->delete();
    });
    $result = booking(fn (): array => app(BackpostHistory::class)->handle());

    expect($result['failed'])->toBe([])->and(booking(fn (): int => JournalEntry::query()->where('source_type', 'stock_issue')->count()))->toBe(1);
});

it('reports an unmapped stock account without failing the issue', function (): void {
    $store = spStore();
    $rice = spItem('Rice');
    spStock($store, $rice, '50', '100');
    $department = booking(fn (): Department => Department::factory()->create());
    booking(fn () => Account::query()->where('system_key', 'food_cost')->update(['system_key' => null]));

    $issue = booking(fn () => app(IssueStock::class)->handle($store->id, $department->id, [['item_id' => $rice->id, 'quantity' => '5']], 'Canteen'));

    expect($issue->totalCost)->toBe('500.00')->and(booking(fn (): int => JournalEntry::query()->where('source_type', 'stock_issue')->count()))->toBe(0);
});
