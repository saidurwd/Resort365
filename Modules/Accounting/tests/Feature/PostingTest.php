<?php

/*
| Accounting (Step 4.2). "Done when": after the guest journey the trial balance balances and Customer
| Advances equals the deposits held for future stays.
|
| Rooms 401/402 (C04), 701–703 (C07), 801–803 (C08); 6,000 a night + SC 10% + VAT 15% = 7,590.00.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\ChangePeriodStatus;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\SaveAccountMappings;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\PostingService;
use Modules\Billing\Actions\IssueInvoice;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\RefundPayment;
use Modules\Billing\Actions\TransferToCityLedger;
use Modules\Billing\Actions\VoidFolioLine;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;
use Modules\FrontOffice\Actions\RunNightAudit;
use Modules\Guest\Models\Company;
use Modules\Property\Models\Property;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(function (): void {
        Tax::query()->where('code', 'SC')->update(['name' => 'Service charge']);
        Tax::query()->where('code', 'VAT')->update(['name' => 'VAT']);
        app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id'));
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(glDay()->startOfMonth()->subMonths(2)->toDateString());
    });
    Notification::fake();
});

function glDay(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

/** Net debit (credit when negative) of the account with the system key. */
function glNet(string $key): string
{
    return booking(function () use ($key): string {
        $id = Account::query()->where('system_key', $key)->value('id');

        return number_format((float) JournalLine::query()->where('account_id', $id)->sum('debit') - (float) JournalLine::query()->where('account_id', $id)->sum('credit'), 2, '.', '');
    });
}

/**
 * The entry's lines as "system key => net debit".
 *
 * @return array<string, string>
 */
function glEntry(string $type, int $id, string $event): array
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

function glEntries(): int
{
    return booking(fn (): int => JournalEntry::query()->whereNotNull('source_type')->count());
}

/**
 * @return array{string, string} total debit and credit of the ledger
 */
function glTrialBalance(): array
{
    return booking(fn (): array => [(string) JournalLine::query()->sum('debit'), (string) JournalLine::query()->sum('credit')]);
}

function glStay(int $from, int $nights): Reservation
{
    $reservation = bookStay(['401'], glDay()->addDays($from)->toDateString(), glDay()->addDays($from + $nights)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);
    booking(fn () => app(StayOperations::class)->checkIn($reservation->id));

    return freshReservation($reservation->id);
}

function glPay(int $reservationId, string $amount, PaymentMethod $method = PaymentMethod::Cash, ?int $folioId = null): Payment
{
    return booking(fn (): Payment => RecordPayment::make()->handle(new NewPayment($reservationId, $method, $amount, folioId: $folioId)));
}

it('posts a deposit to customer advances, once', function (): void {
    $reservation = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    $payment = glPay($reservation->id, '3000.00', PaymentMethod::Card);

    expect(glEntry('payment', $payment->id, 'received'))->toBe(['card_clearing' => '3000.00', 'customer_advances' => '-3000.00']);

    booking(fn () => app(PostingService::class)->payment($payment->id));
    expect(glEntries())->toBe(1)->and(glTrialBalance())->toBe(['3000.00', '3000.00']);

    $line = booking(fn (): JournalLine => JournalLine::query()->firstOrFail());
    expect($line->property_id)->toBe(bookingIds()['property']);
});

it('posts the revenue of a night at night audit: guest ledger against room revenue, service charge and VAT', function (): void {
    glStay(-1, 3);
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));
    $nights = booking(fn (): int => FolioLine::query()->where('line_type', 'charge')->count());
    $day = glDay()->subDay()->toDateString();

    expect($nights)->toBeGreaterThan(0)
        ->and(glEntry('property_day', bookingIds()['property'], 'revenue:'.$day))->toBe([
            'guest_ledger' => number_format(7590 * $nights, 2, '.', ''), 'room_revenue' => number_format(-6000 * $nights, 2, '.', ''),
            'service_charge_payable' => number_format(-600 * $nights, 2, '.', ''), 'vat_payable' => number_format(-990 * $nights, 2, '.', ''),
        ]);
});

it('posts extras on the folio with the day, under their own revenue account', function (): void {
    $stay = glStay(-1, 3);
    booking(fn () => app(FolioPostingContract::class)->postCharge(new FolioCharge($stay->id, app(FolioPostingContract::class)->chargeCodeId('SPA'), '2000.00')));
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));
    $entry = glEntry('property_day', bookingIds()['property'], 'revenue:'.glDay()->subDay()->toDateString());

    expect($entry)->toHaveKey('spa_revenue')->and($entry['spa_revenue'])->toBe('-2000.00');
});

it('leaves out charges whose revenue another module posts', function (): void {
    $stay = glStay(-1, 3);
    booking(fn () => app(FolioPostingContract::class)->postCharge(new FolioCharge($stay->id, app(FolioPostingContract::class)->chargeCodeId('FNB'), '900.00', revenuePostedBySource: true)));
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));

    expect(glEntry('property_day', bookingIds()['property'], 'revenue:'.glDay()->subDay()->toDateString()))->not->toHaveKey('food_revenue');
});

it('reverses a charge that is voided after its day was posted', function (): void {
    glStay(-1, 3);
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));
    $line = booking(fn (): FolioLine => FolioLine::query()->where('line_type', 'charge')->orderBy('id')->firstOrFail());
    booking(fn () => VoidFolioLine::make()->handle($line, 'Entered by mistake'));
    $mirror = glEntry('folio_line', $line->id, 'voided');

    expect($mirror['room_revenue'])->toBe('6000.00')->and($mirror['guest_ledger'])->toBe('-7590.00');
});

it('applies deposits at check-out: customer advances to guest ledger', function (): void {
    $reservation = bookStay(['401'], glDay()->toDateString(), glDay()->addDays(2)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);
    $deposit = glPay($reservation->id, '2000.00', PaymentMethod::Cash);
    booking(fn () => app(StayOperations::class)->checkIn($reservation->id));
    $folio = booking(fn (): Folio => Folio::query()->where('reservation_id', $reservation->id)->sole());
    $invoice = booking(fn () => IssueInvoice::make()->handle($folio));

    expect(glEntry('invoice', $invoice->id, 'deposits_applied'))->toBe(['customer_advances' => '2000.00', 'guest_ledger' => '-2000.00'])
        ->and(glNet('customer_advances'))->toBe('0.00')
        ->and($deposit->id)->toBeInt();
});

it('moves a balance to the city ledger', function (): void {
    $stay = glStay(-1, 3);
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));
    $folio = booking(fn (): Folio => Folio::query()->where('reservation_id', $stay->id)->sole());
    $company = booking(fn (): Company => Company::factory()->create(['credit_limit' => '0']));
    $entry = booking(fn () => TransferToCityLedger::make()->handle($folio->fresh(), $company->id));

    expect(glEntry('city_ledger_entry', $entry->id, 'transferred'))->toBe(['city_ledger' => $entry->amount, 'guest_ledger' => '-'.$entry->amount]);
});

it('keeps a cancellation fee out of customer advances and refunds the rest', function (): void {
    $reservation = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    glPay($reservation->id, '3000.00');
    booking(fn () => CancelReservation::make()->handle(freshReservation($reservation->id), 'Plans changed'));
    booking(fn () => Reservation::query()->whereKey($reservation->id)->update(['cancellation_fee' => '1000.00']));
    booking(fn () => app(PostingService::class)->cancellationFee($reservation->id));
    $refund = booking(fn (): Payment => RefundPayment::make()->handle(new NewRefund($reservation->id, RefundKind::Cancellation, PaymentMethod::Cash, '2000.00', 'Cancelled')));

    expect(glEntry('reservation', $reservation->id, 'cancellation_fee'))->toBe(['customer_advances' => '1000.00', 'cancellation_revenue' => '-1000.00'])
        ->and(glEntry('payment', $refund->id, 'refunded'))->toBe(['customer_advances' => '2000.00', 'cash_on_hand' => '-2000.00'])
        ->and(glNet('customer_advances'))->toBe('0.00');
});

it('never retains more fee than was paid', function (): void {
    $reservation = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    glPay($reservation->id, '500.00');
    booking(fn () => CancelReservation::make()->handle(freshReservation($reservation->id), 'Plans changed'));
    booking(fn () => Reservation::query()->whereKey($reservation->id)->update(['cancellation_fee' => '1000.00']));
    booking(fn () => app(PostingService::class)->cancellationFee($reservation->id));

    expect(glEntry('reservation', $reservation->id, 'cancellation_fee')['cancellation_revenue'])->toBe('-500.00');
});

it('posts into the next open period when the date is closed, and says so in the reference', function (): void {
    $reservation = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    booking(function (): void {
        foreach (FiscalPeriod::query()->orderBy('number')->limit(3)->get() as $period) {
            app(ChangePeriodStatus::class)->handle($period, PeriodStatus::Closed);
        }
    });
    $payment = glPay($reservation->id, '1000.00');
    $entry = booking(fn (): JournalEntry => JournalEntry::query()->where('source_id', $payment->id)->sole());

    expect($entry->entry_date->toDateString())->toBe(booking(fn (): string => FiscalPeriod::query()->where('number', 4)->sole()->starts_on->toDateString()))
        ->and($entry->reference)->toContain($payment->receipt_no)->toContain(glDay()->toDateString());
});

it('does not fail the operation when the ledger cannot take the posting', function (): void {
    $reservation = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    booking(fn () => DB::table('fiscal_periods')->delete());
    booking(fn () => DB::table('fiscal_years')->delete());

    $payment = glPay($reservation->id, '1000.00');

    expect($payment->exists)->toBeTrue()->and(glEntries())->toBe(0)
        ->and(booking(fn (): int => JournalEntry::query()->where('status', 'draft')->count()))->toBe(0);
});

it('uses the tenant\'s mapping instead of the default account', function (): void {
    $bank = booking(fn (): int => Account::query()->where('system_key', 'bank')->value('id'));
    booking(fn () => SaveAccountMappings::make()->handle(['method:cash' => $bank]));
    $reservation = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    $payment = glPay($reservation->id, '1000.00', PaymentMethod::Cash);

    expect(glEntry('payment', $payment->id, 'received'))->toBe(['bank' => '1000.00', 'customer_advances' => '-1000.00']);
});

it('refuses a mapping to a group account or an unknown key', function (): void {
    $group = booking(fn (): int => Account::query()->where('is_group', true)->value('id'));

    expect(fn () => booking(fn () => SaveAccountMappings::make()->handle(['method:cash' => $group])))->toThrow(AccountingRuleViolated::class)
        ->and(fn () => booking(fn () => SaveAccountMappings::make()->handle(['nonsense' => null])))->toThrow(AccountingRuleViolated::class);
});

it('posts earlier events with the back-posting action, and only once', function (): void {
    $reservation = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    glPay($reservation->id, '1000.00');
    glStay(-1, 3);
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));
    $before = glEntries();
    booking(function (): void {
        DB::table('journal_lines')->delete();
        DB::table('journal_entries')->delete();
    });

    $result = booking(fn (): array => app(BackpostHistory::class)->handle());

    expect($result['failed'])->toBe([])->and($result['posted'])->toBe($before)->and(glEntries())->toBe($before);
    expect(booking(fn (): array => app(BackpostHistory::class)->handle()))->toBe(['posted' => 0, 'failed' => []]);
});

it('reports what back-posting could not post', function (): void {
    glPay(bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString())->id, '1000.00');
    booking(function (): void {
        DB::table('journal_lines')->delete();
        DB::table('journal_entries')->delete();
        DB::table('fiscal_periods')->delete();
        DB::table('fiscal_years')->delete();
    });

    $result = booking(fn (): array => app(BackpostHistory::class)->handle());

    expect($result['posted'])->toBe(0)->and($result['failed'])->toHaveCount(1);
});

it('keeps the trial balance balanced and customer advances equal to the deposits held', function (): void {
    $future = bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString());
    $deposit = glPay($future->id, '2500.00', PaymentMethod::Card);
    $other = bookStay(['701'], glDay()->addDays(20)->toDateString(), glDay()->addDays(22)->toDateString());
    glPay($other->id, '1800.00', PaymentMethod::Cash);
    glStay(-1, 3);
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));

    [$debit, $credit] = glTrialBalance();
    $held = booking(fn (): string => (string) Payment::query()->where('payment_type', 'deposit')->sum('amount'));

    expect($debit)->toBe($credit)
        ->and(glNet('customer_advances'))->toBe('-'.number_format((float) $held, 2, '.', ''))
        ->and($deposit->id)->toBeInt();
});

it('shows the mapping screen to an accountant, saves it, and refuses a waiter', function (): void {
    staffUser(DefaultRole::Accountant);
    $bank = booking(fn (): int => Account::query()->where('system_key', 'bank')->value('id'));

    get(tenantUrl('sunrise', '/accounting/mappings'))->assertOk()->assertSeeHtml('data-mapping="method:cash"')->assertSeeHtml('data-mapping="charge:SPA"')->assertSeeHtml('data-mapping="guest_ledger"');
    put(tenantUrl('sunrise', '/accounting/mappings'), ['mappings' => ['method:cash' => $bank, 'method:card' => '']])->assertSessionHas('success');
    expect(booking(fn (): int => AccountMapping::query()->count()))->toBe(1);

    $group = booking(fn (): int => Account::query()->where('is_group', true)->value('id'));
    put(tenantUrl('sunrise', '/accounting/mappings'), ['mappings' => ['method:cash' => $group]])->assertSessionHas('error');
    put(tenantUrl('sunrise', '/accounting/mappings'), ['mappings' => ['method:cash' => 999999]])->assertSessionHasErrors('mappings.method:cash');

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/accounting/mappings'))->assertForbidden();
    put(tenantUrl('sunrise', '/accounting/mappings'), ['mappings' => []])->assertForbidden();
});

it('filters the journal list to automatic entries', function (): void {
    staffUser(DefaultRole::Accountant);
    glPay(bookStay(['401'], glDay()->addDays(10)->toDateString(), glDay()->addDays(12)->toDateString())->id, '1000.00');

    get(tenantUrl('sunrise', '/accounting/journals/data?source=automatic'))->assertOk()->assertJsonPath('recordsFiltered', 1);
    get(tenantUrl('sunrise', '/accounting/journals/data?source=manual'))->assertOk()->assertJsonPath('recordsFiltered', 0);
});
