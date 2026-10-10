<?php

/*
| Accounting (Step 4.3), restaurant postings. "Done when": a day of restaurant sales produces food and beverage
| revenue by outlet, with room-charge bills not counted twice.
|
| The order setup's bill: 2 naan (100.00) and a mojito (350.00) = 550.00 + SC 55.00 + VAT 90.75 = 695.75.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\SaveAccount;
use Modules\Accounting\Actions\SaveAccountMappings;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\PostingService;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;
use Modules\FrontOffice\Actions\PostStayCharges;
use Modules\Guest\Models\Company;
use Modules\Restaurant\Actions\ClosePosSession;
use Modules\Restaurant\Actions\SaveMenuCategory;
use Modules\Restaurant\Contracts\RestaurantFacts;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\PosSession;

use function Pest\Laravel\get;

require_once __DIR__.'/../../../Restaurant/tests/Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(function (): void {
        Tax::query()->where('code', 'SC')->update(['name' => 'Service charge']);
        Tax::query()->where('code', 'VAT')->update(['name' => 'VAT']);
        app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id'));
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(roomDay()->startOfMonth()->subMonths(2)->toDateString());
    });
    Notification::fake();
});

/** Net debit (credit when negative) of the account with the system key, or of the account id. */
function rxNet(string|int $account): string
{
    return booking(function () use ($account): string {
        $id = is_int($account) ? $account : Account::query()->where('system_key', $account)->value('id');

        return number_format((float) JournalLine::query()->where('account_id', $id)->sum('debit') - (float) JournalLine::query()->where('account_id', $id)->sum('credit'), 2, '.', '');
    });
}

/**
 * The entry's lines as "system key => net debit".
 *
 * @return array<string, string>
 */
function rxEntry(int $billId, string $event = 'settled', string $type = 'pos_bill'): array
{
    return booking(function () use ($billId, $event, $type): array {
        $entry = JournalEntry::query()->where('source_type', $type)->where('source_id', $billId)->where('source_event', $event)->firstOrFail();
        $lines = [];

        foreach (JournalLine::query()->where('journal_entry_id', $entry->id)->with('account')->get() as $line) {
            $key = $line->account->system_key ?? $line->account->code;
            $lines[$key] = number_format((float) ($lines[$key] ?? 0) + (float) $line->debit - (float) $line->credit, 2, '.', '');
        }

        return $lines;
    });
}

/**
 * @param  array<string, mixed>  $setup
 */
function rxDrinks(array $setup): void
{
    booking(fn () => MenuCategory::query()->whereKey(MenuItem::query()->findOrFail($setup['items']['mojito'])->menu_category_id)->update(['revenue_class' => 'beverage']));
}

/**
 * A printed bill (naan and mojito) paid in full by the method, with the cashier's session open.
 *
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $payment
 * @return array<string, mixed>
 */
function rxBill(array $setup, string $method = 'cash', array $payment = [], string $table = 'T1'): array
{
    $bill = naanAndMojito($setup, $table);
    billApi("bills/{$bill['id']}/payments", ['method' => $method, 'amount' => '695.75', 'reference' => 'ref', ...$payment])->assertOk();

    return $bill;
}

it('posts a card bill: tender, food and beverage revenue, service charge, VAT and the tip', function (): void {
    $setup = billSetup();
    rxDrinks($setup);
    cashierOn($setup);
    $bill = rxBill($setup, 'card', ['tip' => '50']);

    expect(rxEntry($bill['id']))->toBe([
        'card_clearing' => '745.75', 'food_revenue' => '-200.00', 'beverage_revenue' => '-350.00', 'service_charge_payable' => '-55.00', 'vat_payable' => '-90.75', 'tips_payable' => '-50.00',
    ]);

    booking(fn () => app(PostingService::class)->restaurantBill($bill['id']));
    expect(booking(fn (): int => JournalEntry::query()->where('source_type', 'pos_bill')->count()))->toBe(1);
});

it('posts a cash bill to cash on hand', function (): void {
    $setup = billSetup();
    cashierOn($setup);
    $bill = rxBill($setup, 'cash');

    expect(rxEntry($bill['id'])['cash_on_hand'])->toBe('695.75');
});

it('counts food and beverage by the category, a sub-category following its parent', function (): void {
    $setup = billSetup();
    $category = booking(fn (): MenuCategory => MenuCategory::query()->findOrFail(MenuItem::query()->findOrFail($setup['items']['mojito'])->menu_category_id));
    $parent = booking(fn (): MenuCategory => SaveMenuCategory::make()->handle($category->property_id, null, ['name' => ['en' => 'Drinks'], 'revenue_class' => 'beverage']));
    booking(fn () => $category->forceFill(['parent_id' => $parent->id, 'revenue_class' => null])->save());
    cashierOn($setup);
    $bill = rxBill($setup);

    expect(rxEntry($bill['id']))->toHaveKey('beverage_revenue')->and(rxEntry($bill['id'])['beverage_revenue'])->toBe('-350.00');
});

it('books each outlet\'s sales to the accounts mapped for it (done when)', function (): void {
    $setup = billSetup();
    rxDrinks($setup);
    $outletId = $setup['terminal']->outlet_id;
    [$food, $drink] = booking(function () use ($outletId): array {
        $group = Account::query()->where('code', '4200')->value('id');
        $make = fn (string $code, string $name): Account => app(SaveAccount::class)->handle(null, ['code' => $code, 'name' => $name, 'type' => 'income', 'parent_id' => $group, 'is_group' => false]);
        $food = $make('4291', 'Main Restaurant food');
        $drink = $make('4292', 'Main Restaurant beverage');
        app(SaveAccountMappings::class)->handle(['outlet:'.$outletId.':food' => $food->id, 'outlet:'.$outletId.':beverage' => $drink->id]);

        return [$food->id, $drink->id];
    });
    cashierOn($setup);
    rxBill($setup, 'cash', [], 'T1');
    rxBill($setup, 'card', [], 'T2');

    expect(rxNet($food))->toBe('-400.00')->and(rxNet($drink))->toBe('-700.00')->and(rxNet('food_revenue'))->toBe('0.00');
});

it('posts a room-charge bill against the guest ledger and never counts its revenue twice', function (): void {
    $setup = billSetup();
    rxDrinks($setup);
    $stay = guestIn('401');
    cashierOn($setup);
    $bill = naanAndMojito($setup);
    chargeRoom($bill['id'], '695.75', $stay->id, ['signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='])->assertOk();

    expect(rxEntry($bill['id']))->toBe(['guest_ledger' => '695.75', 'food_revenue' => '-200.00', 'beverage_revenue' => '-350.00', 'service_charge_payable' => '-55.00', 'vat_payable' => '-90.75']);

    // The night's sweep of the folio leaves the restaurant line to the bill's own posting.
    booking(fn () => PostStayCharges::make()->handle($stay->id));
    $day = roomDay()->toDateString();
    $charges = booking(fn (): array => app(LedgerFacts::class)->chargesOn(bookingIds()['property'], $day));
    $swept = booking(fn (): ?JournalEntry => app(PostingService::class)->nightRevenue(bookingIds()['property'], $day));

    expect(collect($charges)->pluck('chargeCode')->all())->not->toContain('FNB');
    $sweep = rxEntry(bookingIds()['property'], 'revenue:'.$day, 'property_day');
    expect($sweep)->not->toHaveKey('food_revenue')->and($sweep)->toHaveKey('room_revenue')->and($swept)->not->toBeNull();
    expect(rxNet('food_revenue'))->toBe('-200.00');
});

it('posts a city-ledger bill to the city ledger', function (): void {
    $setup = billSetup();
    cashierOn($setup);
    $company = booking(fn (): Company => Company::factory()->create(['credit_limit' => '0']));
    $bill = rxBill($setup, 'city_ledger', ['company_id' => $company->id]);

    expect(rxEntry($bill['id'])['city_ledger'])->toBe('695.75');
});

it('posts nothing for a complimentary bill', function (): void {
    $setup = billSetup();
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($manager, '9999')->assertRedirect();
    openSession($setup['terminal'], $manager->id);
    $bill = naanAndMojito($setup);
    $approval = billApi('approvals', ['action' => 'bill.comp', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $bill['id']])->json('approval_id');
    billApi("bills/{$bill['id']}/comp", ['comp_reason' => 'service_recovery', 'approval_id' => $approval])->assertOk();

    expect(booking(fn (): int => JournalEntry::query()->where('source_type', 'pos_bill')->count()))->toBe(0);
});

it('reverses the whole entry when a settled bill is voided', function (): void {
    $setup = billSetup();
    rxDrinks($setup);
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($manager, '9999')->assertRedirect();
    openSession($setup['terminal'], $manager->id);
    $bill = rxBill($setup, 'card', ['tip' => '50']);
    $approval = billApi('approvals', ['action' => 'bill.void', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $bill['id']])->json('approval_id');
    billApi("bills/{$bill['id']}/void", ['reason' => 'Wrong table', 'food_prepared' => false, 'approval_id' => $approval])->assertOk();

    expect(booking(fn () => JournalEntry::query()->where('source_type', 'pos_bill')->sole()->status))->toBe(JournalStatus::Reversed)
        ->and(rxNet('card_clearing'))->toBe('0.00')->and(rxNet('food_revenue'))->toBe('0.00')->and(rxNet('tips_payable'))->toBe('0.00')
        ->and(booking(fn (): int => JournalEntry::query()->whereNotNull('reverses_id')->count()))->toBe(1);

    booking(fn () => app(PostingService::class)->restaurantBillVoided($bill['id']));
    expect(booking(fn (): int => JournalEntry::query()->whereNotNull('reverses_id')->count()))->toBe(1);
});

it('posts a cash shortage as an expense and an overage as income', function (): void {
    $setup = billSetup();
    $session = cashierOn($setup);
    booking(fn () => ClosePosSession::make()->handle(PosSession::query()->findOrFail($session->id), ['1000' => 1, '500' => 1, '100' => 4, '50' => 1, '20' => 1, '10' => 1], 'Counted wrong', (int) $session->opened_by));

    expect(rxEntry($session->id, 'variance', 'pos_session'))->toBe(['cash_short_expense' => '20.00', 'cash_on_hand' => '-20.00']);
    $second = openSession($setup['terminal'], (int) $session->opened_by);
    booking(fn () => ClosePosSession::make()->handle(PosSession::query()->findOrFail($second->id), ['1000' => 2, '10' => 2], 'Found extra', (int) $session->opened_by));

    expect(rxEntry($second->id, 'variance', 'pos_session'))->toBe(['cash_on_hand' => '20.00', 'cash_over_income' => '-20.00']);
});

it('posts earlier bills with back-posting, a voided one reversed, once', function (): void {
    $setup = billSetup();
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($manager, '9999')->assertRedirect();
    openSession($setup['terminal'], $manager->id);
    rxBill($setup, 'cash', [], 'T1');
    $voided = rxBill($setup, 'card', [], 'T2');
    $approval = billApi('approvals', ['action' => 'bill.void', 'manager_id' => $manager->id, 'pin' => '9999', 'subject_id' => $voided['id']])->json('approval_id');
    billApi("bills/{$voided['id']}/void", ['reason' => 'Wrong table', 'food_prepared' => false, 'approval_id' => $approval])->assertOk();
    $count = booking(fn (): int => JournalEntry::query()->count());
    booking(function (): void {
        DB::table('journal_lines')->delete();
        DB::table('journal_entries')->update(['reverses_id' => null, 'reversed_by_id' => null]);
        DB::table('journal_entries')->delete();
    });

    $result = booking(fn (): array => app(BackpostHistory::class)->handle());

    expect($result['failed'])->toBe([])->and(booking(fn (): int => JournalEntry::query()->count()))->toBe($count)
        ->and(rxNet('card_clearing'))->toBe('0.00')->and(rxNet('cash_on_hand'))->toBe('695.75')
        ->and(booking(fn (): array => app(BackpostHistory::class)->handle()))->toBe(['posted' => 0, 'failed' => []]);
});

it('lists the outlets on the mapping screen and maps categories through the form', function (): void {
    $setup = billSetup();
    staffUser(DefaultRole::Accountant);
    $outletId = $setup['terminal']->outlet_id;

    get(tenantUrl('sunrise', '/accounting/mappings'))->assertOk()->assertSeeHtml('data-mapping="outlet:'.$outletId.':food"')->assertSeeHtml('data-mapping="outlet:'.$outletId.':beverage"')->assertSeeHtml('data-mapping="tips_payable"');
});

it('keeps a settled bill\'s revenue by class in the facts, net of discounts', function (): void {
    $setup = billSetup();
    rxDrinks($setup);
    cashierOn($setup);
    $bill = rxBill($setup);
    $fact = booking(fn () => app(RestaurantFacts::class)->bill($bill['id']));

    expect($fact?->revenue)->toBe(['food' => '200.00', 'beverage' => '350.00'])->and($fact?->tips)->toBe('0.00')->and($fact?->payments)->toBe(['cash' => '695.75']);
});
