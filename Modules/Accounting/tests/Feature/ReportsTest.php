<?php

/*
| Accounting (Step 4.5), the financial reports. "Done when": clicking any P&L figure drills down to the journal
| lines and then to the original booking, bill or payment. Phase 4 exit: the trial balance always balances and
| room revenue equals the night audit's postings.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\CreateVoucher;
use Modules\Accounting\Actions\SaveJournalDraft;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\DTOs\Report;
use Modules\Accounting\DTOs\ReportFilter;
use Modules\Accounting\DTOs\VoucherData;
use Modules\Accounting\Enums\VoucherType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\PostingService;
use Modules\Accounting\Services\ReportService;
use Modules\Billing\Actions\IssueInvoice;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Actions\TransferToCityLedger;
use Modules\Billing\Contracts\DailyTakings;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Folio;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;
use Modules\FrontOffice\Actions\RunNightAudit;
use Modules\Guest\Models\Company;
use Modules\Property\Models\Property;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Models\Reservation;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use function Pest\Laravel\get;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(function (): void {
        Tax::query()->where('code', 'SC')->update(['name' => 'Service charge']);
        Tax::query()->where('code', 'VAT')->update(['name' => 'VAT']);
        app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id'));
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(CarbonImmutable::parse(Property::query()->where('code', 'CXB')->value('business_date'))->startOfMonth()->subMonths(2)->toDateString());
    });
    Notification::fake();
});

function rpDay(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

function rpFilter(?string $property = null): ReportFilter
{
    return new ReportFilter(rpDay()->subMonths(2)->startOfMonth()->toDateString(), rpDay()->addMonth()->toDateString(), $property);
}

function rpAccount(string $code): int
{
    return booking(fn (): int => Account::query()->where('code', $code)->value('id'));
}

/** A guest in house from yesterday for three nights, and the night audit run. */
function rpAuditedStay(): Reservation
{
    $reservation = bookStay(['401'], rpDay()->subDay()->toDateString(), rpDay()->addDays(2)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);
    booking(fn () => app(StayOperations::class)->checkIn($reservation->id));
    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));

    return freshReservation($reservation->id);
}

function rpVoucher(string $amount = '25000.00', ?int $propertyId = null, string $type = 'income', string $expenseCode = '6130'): void
{
    booking(fn () => app(CreateVoucher::class)->handle(new VoucherData(
        VoucherType::from($type), $propertyId ?? bookingIds()['property'], rpDay()->toDateString(), rpAccount($type === 'income' ? '4230' : $expenseCode), rpAccount('1120'), $amount, 'Voucher '.$type,
    )));
}

/**
 * @return array<string, string> the report's rows as "label => first figure"
 */
function rpFigures(Report $report): array
{
    $figures = [];

    foreach ($report->rows as $row) {
        $figures[$row['cells'][0]] = $row['cells'][1] ?? '';
    }

    return $figures;
}

it('keeps the trial balance balanced and room revenue equal to the night audit (phase 4 exit)', function (): void {
    rpAuditedStay();
    rpVoucher();
    $deposit = bookStay(['701'], rpDay()->addDays(9)->toDateString(), rpDay()->addDays(11)->toDateString());
    booking(fn () => RecordPayment::make()->handle(new NewPayment($deposit->id, PaymentMethod::Card, '2000.00')));
    $audited = rpDay()->subDay()->toDateString();

    $trial = booking(fn () => app(ReportService::class)->trialBalance(rpFilter()));
    $takings = booking(fn () => app(DailyTakings::class)->forDate(bookingIds()['property'], $audited));
    $profit = rpFigures(booking(fn () => app(ReportService::class)->profitAndLoss(rpFilter())));

    expect($trial->notes[0])->toContain('balances:')
        ->and($profit['4110 · Room revenue'])->toBe(number_format((float) $takings->charges['room']['net'], 2));
});

it('balances the balance sheet and agrees the cash flow with the cash accounts', function (): void {
    rpAuditedStay();
    rpVoucher('25000.00');
    rpVoucher('4000.00', null, 'expense');

    $sheet = booking(fn () => app(ReportService::class)->balanceSheet(rpFilter()));
    $cash = booking(fn () => app(ReportService::class)->cashFlow(rpFilter()));

    expect($sheet->notes[0])->toContain('balances:')->and($cash->notes[0])->toContain('agrees');
    $figures = rpFigures($sheet);
    expect($figures['Total assets'])->toBe($figures['Liabilities and equity']);
});

it('drills from a profit and loss figure to the ledger, the journal entry and the booking, payment and invoice behind it', function (): void {
    staffUser(DefaultRole::Accountant);
    $stay = rpAuditedStay();
    $folio = booking(fn (): Folio => Folio::query()->where('reservation_id', $stay->id)->sole());
    $payment = booking(fn () => RecordPayment::make()->handle(new NewPayment($stay->id, PaymentMethod::Card, '1000.00', folioId: $folio->id)));
    $filter = ['from' => rpFilter()->from, 'to' => rpFilter()->to];

    // 1. The P&L: room revenue links to the ledger of that account.
    $profit = get(tenantUrl('sunrise', '/accounting/reports/profit-loss?'.http_build_query($filter)))->assertOk()->assertSeeHtml('data-drill')->getContent();
    preg_match('/href="([^"]+)" data-drill>[^<]*<\/a>/', substr($profit, (int) strpos($profit, '4110 · Room revenue')), $m);
    expect($m[1] ?? '')->toContain('/accounting/reports/ledger')->toContain('account='.rpAccount('4110'));

    // 2. The ledger lists the night's entry, which links to the entry page.
    $ledger = get(html_entity_decode($m[1]))->assertOk()->assertSee('Room and guest charges')->getContent();
    preg_match('/href="([^"]*\/accounting\/journals\/\d+)"/', $ledger, $entry);

    // 3. The entry page lists its source: the day's flash report and the booking.
    get($entry[1])->assertOk()->assertSeeHtml('data-sources')->assertSee('Booking '.$stay->code)->assertSee('Flash report');

    // A payment's entry leads to its receipt and booking; an invoice's to its PDF.
    $paymentEntry = booking(fn (): JournalEntry => JournalEntry::query()->where('source_type', 'payment')->where('source_id', $payment->id)->sole());
    get(tenantUrl('sunrise', '/accounting/journals/'.$paymentEntry->id))->assertOk()->assertSee('Payment receipt '.$payment->receipt_no)->assertSeeHtml('/billing/payments/'.$payment->id.'/receipt');
    $deposit = bookStay(['701'], rpDay()->toDateString(), rpDay()->addDays(2)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);
    booking(fn () => RecordPayment::make()->handle(new NewPayment($deposit->id, PaymentMethod::Cash, '500.00')));
    $invoice = booking(fn () => IssueInvoice::make()->handle(Folio::query()->where('reservation_id', $deposit->id)->sole()));
    $invoiceEntry = booking(fn (): JournalEntry => JournalEntry::query()->where('source_type', 'invoice')->where('source_id', $invoice->id)->sole());
    get(tenantUrl('sunrise', '/accounting/journals/'.$invoiceEntry->id))->assertOk()->assertSeeHtml('/billing/invoices/'.$invoice->id.'/pdf')->assertSee('Booking '.$deposit->code);
});

it('leads a voucher\'s entry to the voucher, and a manual entry to nothing', function (): void {
    staffUser(DefaultRole::Accountant);
    rpVoucher();
    $voucherEntry = booking(fn (): JournalEntry => JournalEntry::query()->where('source_type', 'voucher')->sole());
    $manual = booking(fn (): JournalEntry => app(SaveJournalDraft::class)->handle(null, ['entry_date' => rpDay()->toDateString(), 'description' => 'Manual'], [
        ['account_id' => rpAccount('1110'), 'debit' => '10.00'], ['account_id' => rpAccount('3100'), 'credit' => '10.00'],
    ]));

    get(tenantUrl('sunrise', '/accounting/journals/'.$voucherEntry->id))->assertOk()->assertSeeHtml('data-source');
    get(tenantUrl('sunrise', '/accounting/journals/'.$manual->id))->assertOk()->assertDontSeeHtml('data-sources');
});

it('shows each property and the consolidated total side by side, and filters to one property', function (): void {
    $second = booking(fn (): Property => Property::factory()->create(['code' => 'DHK', 'name' => 'Sunrise Dhaka']));
    rpVoucher('25000.00', bookingIds()['property']);
    rpVoucher('10000.00', $second->id);
    $service = app(ReportService::class);

    $all = booking(fn () => $service->profitAndLoss(rpFilter()));
    $one = booking(fn () => $service->profitAndLoss(rpFilter((string) $second->id)));
    $none = booking(fn () => $service->profitAndLoss(rpFilter('none')));

    expect($all->columns)->toBe(['Account', "Sunrise Cox's Bazar", 'Sunrise Dhaka', 'Consolidated'])
        ->and(collect($all->rows)->firstWhere(fn (array $row): bool => $row['cells'][0] === '4230 · Banquet and event revenue')['cells'])->toBe(['4230 · Banquet and event revenue', '25,000.00', '10,000.00', '35,000.00'])
        ->and(rpFigures($one)['Total income'])->toBe('10,000.00')->and($one->columns)->toBe(['Account', 'Total'])
        ->and(rpFigures($none)['Total income'])->toBe('0.00');
});

it('lists a ledger with opening balance, running balance and property and department filters', function (): void {
    rpVoucher('1000.00');
    rpVoucher('500.00', null, 'expense');
    $filter = new ReportFilter(rpDay()->toDateString(), rpDay()->toDateString());
    $report = booking(fn () => app(ReportService::class)->generalLedger($filter, rpAccount('1120')));

    expect(array_map(fn (array $row): string => $row['cells'][5], $report->rows))->toBe(['0.00', '1,000.00', '500.00', '500.00'])
        ->and($report->rows[1]['links'][1])->toContain('/accounting/journals/');

    $later = booking(fn () => app(ReportService::class)->generalLedger(new ReportFilter(rpDay()->addDay()->toDateString(), rpDay()->addDays(2)->toDateString()), rpAccount('1120')));
    expect($later->rows[0]['cells'][5])->toBe('500.00');
});

it('ages the city ledger by company', function (): void {
    $stay = rpAuditedStay();
    $company = booking(fn (): Company => Company::factory()->create(['name' => 'Acme Travel', 'credit_limit' => '0']));
    $folio = booking(fn (): Folio => Folio::query()->where('reservation_id', $stay->id)->sole());
    booking(fn () => TransferToCityLedger::make()->handle($folio->fresh(), $company->id));
    $aged = booking(fn () => app(ReportService::class)->aging(true, rpDay()->addDays(45)->toDateString()));
    $row = collect($aged->rows)->firstWhere(fn (array $row): bool => $row['cells'][0] === 'Acme Travel');

    expect($row)->not->toBeNull()->and($row['cells'][2])->toBe($row['cells'][6])->and($row['cells'][1])->toBe('0.00');
});

it('exports a report to Excel, CSV and PDF, for those who may export', function (): void {
    staffUser(DefaultRole::Accountant);
    rpVoucher();
    $query = '?from='.rpFilter()->from.'&to='.rpFilter()->to;

    get(tenantUrl('sunrise', '/accounting/reports/trial-balance'.$query.'&export=csv'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertSeeHtml('Trial balance')->assertSeeHtml('1120 · Bank accounts');
    $excel = get(tenantUrl('sunrise', '/accounting/reports/profit-loss'.$query.'&export=xlsx'))->assertOk();
    $file = $excel->baseResponse;
    expect($excel->headers->get('Content-Type'))->toContain('spreadsheetml')->and($file)->toBeInstanceOf(BinaryFileResponse::class);
    expect($file instanceof BinaryFileResponse ? substr((string) file_get_contents($file->getFile()->getPathname()), 0, 2) : '')->toBe('PK');
    expect(get(tenantUrl('sunrise', '/accounting/reports/balance-sheet'.$query.'&export=pdf'))->assertOk()->headers->get('Content-Type'))->toContain('application/pdf');

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/accounting/reports/trial-balance'.$query))->assertOk()->assertDontSeeHtml('data-export');
    get(tenantUrl('sunrise', '/accounting/reports/trial-balance'.$query.'&export=csv'))->assertForbidden();
});

it('shows every report to an accountant and keeps others out', function (): void {
    staffUser(DefaultRole::Accountant);
    rpVoucher();

    get(tenantUrl('sunrise', '/accounting/reports'))->assertOk()->assertSeeHtml('data-report="trial-balance"')->assertSeeHtml('data-report="aging-payable"');

    foreach (['trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow', 'departmental', 'aging-receivable', 'aging-payable'] as $report) {
        get(tenantUrl('sunrise', "/accounting/reports/{$report}"))->assertOk()->assertSeeHtml('data-report-table');
    }

    get(tenantUrl('sunrise', '/accounting/reports/ledger'))->assertOk()->assertSee('Choose an account');
    get(tenantUrl('sunrise', '/accounting/reports/ledger?account='.rpAccount('1120')))->assertOk()->assertSeeHtml('data-report-table="ledger"');
    get(tenantUrl('sunrise', '/accounting/reports/trial-balance?property=999999'))->assertSessionHasErrors('property');
    get(tenantUrl('sunrise', '/accounting/reports/nonsense'))->assertNotFound();

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/accounting/reports'))->assertForbidden();
    get(tenantUrl('sunrise', '/accounting/reports/profit-loss'))->assertForbidden();
});

it('posts nothing twice when a report is read: reports only read', function (): void {
    rpAuditedStay();
    $before = booking(fn (): int => JournalEntry::query()->count());
    booking(fn () => app(ReportService::class)->trialBalance(rpFilter()));
    booking(fn () => app(PostingService::class));

    expect(booking(fn (): int => JournalEntry::query()->count()))->toBe($before);
});
