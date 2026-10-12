<?php

/*
| Accounting (Step 5.7), sale consumption and wastage. "Done when": selling 10 burgers posts the food cost of sales
| against the inventory; a comped bill is staff-meal or complimentary expense; a voided bill reverses it.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\SaveAccountMappings;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\PostingService;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Restaurant\Actions\RecordBillConsumption;
use Modules\Restaurant\Actions\RecordWastage;
use Modules\Restaurant\Actions\ReverseBillConsumption;
use Modules\Restaurant\DTOs\NewWastage;
use Modules\Restaurant\Enums\WastageReason;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\SaleConsumption;

require_once __DIR__.'/../../../Restaurant/tests/Support/recipe-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
    booking(function (): void {
        app(SeedStandardUnits::class)->handle();
        Tax::query()->where('code', 'SC')->update(['name' => 'Service charge']);
        Tax::query()->where('code', 'VAT')->update(['name' => 'VAT']);
        app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id'));
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(roomDay()->startOfMonth()->subMonths(2)->toDateString());
    });
});

/**
 * An entry's lines as "system key => net debit".
 *
 * @return array<string, string>
 */
function rpEntry(int $sourceId, string $event, string $type = 'pos_bill'): array
{
    return booking(function () use ($sourceId, $event, $type): array {
        $entry = JournalEntry::query()->where('source_type', $type)->where('source_id', $sourceId)->where('source_event', $event)->firstOrFail();
        $lines = [];

        foreach (JournalLine::query()->where('journal_entry_id', $entry->id)->with('account')->get() as $line) {
            $key = $line->account->system_key ?? $line->account->code;
            $lines[$key] = number_format((float) ($lines[$key] ?? 0) + (float) $line->debit - (float) $line->credit, 2, '.', '');
        }

        return $lines;
    });
}

it('posts the food cost of 10 burgers against the inventory (done when)', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);
    cashierOn($setup);

    $bill = rcSell($setup, $burger, 10);

    // beef 1,600.00 + flour 72.00 + cheddar 360.00 + tomato 38.92 + ketchup 8.40 + oil 17.10
    expect(rpEntry($bill['id'], 'consumed'))->toBe(['food_cost' => '2096.42', 'inventory_food' => '-2096.42']);

    booking(fn () => app(PostingService::class)->saleConsumption($bill['id']));
    expect(booking(fn (): int => JournalEntry::query()->where('source_event', 'consumed')->count()))->toBe(1);
});

it('books a complimentary bill\'s ingredients as staff meals and complimentary expense', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);
    cashierOn($setup);
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $burger->id, 'quantity' => 1])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
    booking(fn () => PosBill::query()->whereKey($bill['id'])->update(['is_complimentary' => true, 'status' => 'settled']));
    booking(fn () => app(RecordBillConsumption::class)->handle(PosBill::query()->findOrFail($bill['id'])));

    expect(rpEntry($bill['id'], 'consumed'))->toBe(['staff_meal_expense' => '209.64', 'inventory_food' => '-209.64']);
});

it('reverses the cost when the bill is voided before the food was made', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);
    cashierOn($setup);
    $bill = rcSell($setup, $burger, 2);
    booking(fn () => app(ReverseBillConsumption::class)->handle(PosBill::query()->findOrFail($bill['id'])));

    $entries = booking(fn () => JournalEntry::query()->where('source_id', $bill['id'])->where('source_type', 'pos_bill')->where('source_event', 'consumed')->first());
    $net = booking(fn (): string => number_format((float) JournalLine::query()->whereHas('account', fn ($query) => $query->where('system_key', 'food_cost'))->sum('debit') - (float) JournalLine::query()->whereHas('account', fn ($query) => $query->where('system_key', 'food_cost'))->sum('credit'), 2, '.', ''));

    expect($entries->status)->toBe(JournalStatus::Reversed)->and($net)->toBe('0.00');
});

it('books wastage as food wastage expense against the inventory', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    $entry = booking(fn () => app(RecordWastage::class)->handle(new NewWastage($store->id, WastageReason::Spoiled, 1, [['item_id' => $items['tomato']->id, 'quantity' => '3']])));

    expect(rpEntry($entry->id, 'wasted', 'wastage_entry'))->toBe(['food_wastage' => '210.00', 'inventory_food' => '-210.00']);
});

it('follows a mapped cost account, and back-posts what could not be posted', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);
    cashierOn($setup);
    $special = booking(fn (): int => Account::query()->where('system_key', 'food_wastage')->value('id'));
    booking(fn () => app(SaveAccountMappings::class)->handle(['consumption:cost:food' => $special]));
    booking(fn () => Account::query()->where('system_key', 'inventory_food')->update(['system_key' => null]));

    $bill = rcSell($setup, $burger, 1);
    expect(booking(fn (): int => SaleConsumption::query()->count()))->toBe(6)->and(booking(fn (): int => JournalEntry::query()->where('source_event', 'consumed')->count()))->toBe(0);

    booking(fn () => Account::query()->where('code', '1310')->update(['system_key' => 'inventory_food']));
    $result = booking(fn (): array => app(BackpostHistory::class)->handle());

    expect($result['failed'])->toBe([])->and(rpEntry($bill['id'], 'consumed'))->toBe(['food_wastage' => '209.64', 'inventory_food' => '-209.64']);
});
