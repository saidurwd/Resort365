<?php

/*
| Accounting (Step 5.8), opening stock and the inventory reconciliation. "Done when" (Phase 5 exit): the stock in the
| ledger equals the stock valuation (to the cent, with stock in transit counted).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\DTOs\ReportFilter;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\ReportService;
use Modules\Inventory\Actions\AdjustStock;
use Modules\Inventory\Actions\CompleteStockCount;
use Modules\Inventory\Actions\DispatchTransfer;
use Modules\Inventory\Actions\IssueStock;
use Modules\Inventory\Actions\RecordOpeningBalance;
use Modules\Inventory\Actions\SaveCountEntries;
use Modules\Inventory\Actions\StartStockCount;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\AdjustmentReason;
use Modules\Inventory\Enums\MovementType;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;

use function Pest\Laravel\get;

require_once __DIR__.'/../../../Procurement/tests/Support/procurement-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pcPrepare();
    booking(function (): void {
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(CarbonImmutable::parse(Property::query()->where('code', 'CXB')->sole()->business_date->toDateString())->startOfMonth()->subMonths(2)->toDateString());
    });
});

/** @return array<string, string> */
function ilRow(): array
{
    $report = booking(fn () => app(ReportService::class)->inventoryReconciliation(new ReportFilter('2000-01-01', now()->addDay()->toDateString())));
    $total = collect($report->rows)->last()['cells'];

    return ['ledger' => str_replace(',', '', $total[1]), 'stock' => str_replace(',', '', $total[2]), 'transit' => str_replace(',', '', $total[3]), 'difference' => str_replace(',', '', $total[4])];
}

it('books opening stock against opening balance equity', function (): void {
    $store = pcStore('KIT');
    $rice = pcItem('Rice');
    $sugar = pcItem('Sugar');
    booking(fn () => app(RecordOpeningBalance::class)->handle($store->id, [['item_id' => $rice->id, 'quantity' => '100', 'unit_cost' => '160'], ['item_id' => $sugar->id, 'quantity' => '50', 'unit_cost' => '118']]));

    $entry = booking(fn (): JournalEntry => JournalEntry::query()->where('source_type', 'stock_opening')->sole());
    $lines = booking(fn (): array => JournalLine::query()->where('journal_entry_id', $entry->id)->with('account')->get()->mapWithKeys(fn (JournalLine $line): array => [$line->account->system_key => (float) $line->debit - (float) $line->credit])->all());

    expect($lines)->toBe(['inventory_food' => 21900.0, 'owners_capital' => -21900.0]);
});

it('agrees with the stock valuation through openings, issues, counts, adjustments and a transfer in transit', function (): void {
    $kitchen = pcStore('KIT');
    $bar = pcStore('BAR');
    $rice = pcItem('Rice');
    $sugar = pcItem('Sugar');
    $department = booking(fn (): Department => Department::factory()->create());
    booking(fn () => app(RecordOpeningBalance::class)->handle($kitchen->id, [['item_id' => $rice->id, 'quantity' => '100', 'unit_cost' => '160'], ['item_id' => $sugar->id, 'quantity' => '50', 'unit_cost' => '118.3333']]));
    booking(fn () => app(IssueStock::class)->handle($kitchen->id, $department->id, [['item_id' => $rice->id, 'quantity' => '12.5']], 'Canteen'));
    booking(fn () => app(AdjustStock::class)->handle($kitchen->id, $sugar->id, false, '2.5', AdjustmentReason::Damage, 'Split sacks'));
    $count = booking(fn () => app(StartStockCount::class)->handle($kitchen->id));
    booking(fn () => app(SaveCountEntries::class)->handle($count, [$rice->id => '85', $sugar->id => '47.5']));
    booking(fn () => app(CompleteStockCount::class)->handle($count->fresh() ?? $count));
    booking(fn () => app(DispatchTransfer::class)->handle($kitchen->id, $bar->id, [['item_id' => $rice->id, 'quantity' => '20']]));

    $row = ilRow();

    expect((float) $row['transit'])->toBeGreaterThan(0.0)->and(abs((float) $row['difference']))->toBeLessThan(0.05)->and($row['ledger'])->not->toBe('0.00');
});

it('shows the comparison on screen and reports an unposted movement as a difference', function (): void {
    $store = pcStore('KIT');
    $rice = pcItem('Rice');
    booking(fn () => app(RecordOpeningBalance::class)->handle($store->id, [['item_id' => $rice->id, 'quantity' => '10', 'unit_cost' => '100']]));
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', '/accounting/reports/inventory'))->assertOk()->assertSee('Inventory against the ledger')->assertSee('1,000.00');
    expect(ilRow()['difference'])->toBe('0.00');

    // A receipt that never reached the ledger shows as a difference.
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $rice->id, '5', '100')));
    expect(ilRow()['difference'])->toBe('-500.00');
});

it('back-posts opening stock once', function (): void {
    $store = pcStore('KIT');
    $rice = pcItem('Rice');
    booking(fn () => app(RecordOpeningBalance::class)->handle($store->id, [['item_id' => $rice->id, 'quantity' => '10', 'unit_cost' => '100']]));
    booking(function (): void {
        JournalLine::query()->whereIn('journal_entry_id', JournalEntry::query()->where('source_type', 'stock_opening')->pluck('id'))->delete();
        JournalEntry::query()->where('source_type', 'stock_opening')->delete();
    });

    $first = booking(fn (): array => app(BackpostHistory::class)->handle());
    $second = booking(fn (): array => app(BackpostHistory::class)->handle());

    expect($first['failed'])->toBe([])->and($second['failed'])->toBe([])->and(booking(fn (): int => JournalEntry::query()->where('source_type', 'stock_opening')->count()))->toBe(1)
        ->and(ilRow()['difference'])->toBe('0.00');
});
