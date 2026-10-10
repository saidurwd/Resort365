<?php

/*
| Accounting (Step 4.3), quick income and expense vouchers: posted when saved, attachments for receipts,
| a void reverses the entry.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Actions\ChangePeriodStatus;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Models\Voucher;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(function (): void {
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(now()->startOfMonth()->subMonths(2)->toDateString());
    });
    Storage::fake('attachments');
});

function vcAccount(string $code): int
{
    return booking(fn (): int => Account::query()->where('code', $code)->value('id'));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vcExpense(array $overrides = []): array
{
    return ['type' => 'expense', 'voucher_date' => now()->toDateString(), 'account_id' => vcAccount('6130'), 'cash_account_id' => vcAccount('1110'), 'amount' => '1150.00', 'tax_amount' => '150.00', 'description' => 'Generator fuel', 'payee' => 'Cox Fuel', ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vcIncome(array $overrides = []): array
{
    return ['type' => 'income', 'voucher_date' => now()->toDateString(), 'account_id' => vcAccount('4230'), 'cash_account_id' => vcAccount('1120'), 'amount' => '25000.00', 'description' => 'Hall rental', ...$overrides];
}

/**
 * @return array<string, string> account code => net debit
 */
function vcLines(Voucher $voucher): array
{
    return booking(function () use ($voucher): array {
        $lines = [];

        foreach (JournalLine::query()->where('journal_entry_id', $voucher->journal_entry_id)->with('account')->get() as $line) {
            $lines[$line->account->code] = number_format((float) $line->debit - (float) $line->credit, 2, '.', '');
        }

        return $lines;
    });
}

function vcStored(string $description): Voucher
{
    return booking(fn (): Voucher => Voucher::query()->where('description', $description)->sole());
}

it('posts an income voucher: cash or bank against the income account', function (): void {
    staffUser(DefaultRole::Accountant);
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome())->assertRedirect()->assertSessionHas('success');
    $voucher = vcStored('Hall rental');

    expect($voucher->voucher_no)->toStartWith('IV-')->and($voucher->status)->toBe(VoucherStatus::Posted)
        ->and(vcLines($voucher))->toBe(['1120' => '25000.00', '4230' => '-25000.00'])
        ->and(booking(fn () => JournalEntry::query()->findOrFail($voucher->journal_entry_id)->only(['source_type', 'source_id', 'status'])))
        ->toBe(['source_type' => 'voucher', 'source_id' => $voucher->id, 'status' => JournalStatus::Posted])
        ->and(booking(fn () => JournalLine::query()->where('journal_entry_id', $voucher->journal_entry_id)->pluck('property_id')->unique()->all()))->toBe([bookingIds()['property']]);
});

it('posts an expense voucher with its input VAT split out', function (): void {
    staffUser(DefaultRole::Accountant);
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense())->assertSessionHas('success');
    $voucher = vcStored('Generator fuel');

    expect($voucher->voucher_no)->toStartWith('EV-')->and(vcLines($voucher))->toEqual(['6130' => '1000.00', '1110' => '-1150.00', '1410' => '150.00']);
});

it('numbers vouchers in sequence per type', function (): void {
    staffUser(DefaultRole::Accountant);
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome(['description' => 'One']));
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome(['description' => 'Two']));
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense(['description' => 'Three']));

    expect([vcStored('One')->voucher_no, vcStored('Two')->voucher_no, vcStored('Three')->voucher_no])->toBe([
        'IV-'.now()->format('Y').'-00001', 'IV-'.now()->format('Y').'-00002', 'EV-'.now()->format('Y').'-00001',
    ]);
});

it('refuses the wrong kind of account, a bad VAT and a closed period, saving nothing', function (): void {
    staffUser(DefaultRole::Accountant);

    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome(['account_id' => vcAccount('6130')]))->assertSessionHas('error');
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense(['cash_account_id' => vcAccount('6130')]))->assertSessionHas('error');
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense(['tax_amount' => '1150.00']))->assertSessionHas('error');
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome(['tax_amount' => '10.00']))->assertSessionHas('error');
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome(['amount' => '0']))->assertSessionHasErrors('amount');
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome(['account_id' => 999999]))->assertSessionHasErrors('account_id');

    booking(function (): void {
        $period = FiscalPeriod::query()->where('starts_on', '<=', now()->subMonths(2)->toDateString())->where('ends_on', '>=', now()->subMonths(2)->toDateString())->sole();
        app(ChangePeriodStatus::class)->handle($period, PeriodStatus::Closed);
    });
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome(['voucher_date' => now()->subMonths(2)->toDateString()]))->assertSessionHas('error');

    expect(booking(fn (): int => Voucher::query()->count()))->toBe(0)->and(booking(fn (): int => JournalEntry::query()->count()))->toBe(0);
});

it('voids a voucher by reversing its entry, once', function (): void {
    staffUser(DefaultRole::Accountant);
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense());
    $voucher = vcStored('Generator fuel');

    post(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}/void"), [])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}/void"), ['reason' => 'Duplicate'])->assertSessionHas('success');
    $voided = vcStored('Generator fuel');

    expect($voided->status)->toBe(VoucherStatus::Void)->and($voided->void_reason)->toBe('Duplicate')
        ->and(booking(fn () => JournalEntry::query()->findOrFail($voided->journal_entry_id)->status))->toBe(JournalStatus::Reversed)
        ->and(booking(fn (): float => (float) JournalLine::query()->where('account_id', vcAccount('1110'))->sum('debit') - (float) JournalLine::query()->where('account_id', vcAccount('1110'))->sum('credit')))->toBe(0.0);

    post(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}/void"), ['reason' => 'Again'])->assertSessionHas('error');
});

it('keeps receipts as attachments', function (): void {
    staffUser(DefaultRole::Accountant);
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense());
    $voucher = vcStored('Generator fuel');

    post(tenantUrl('sunrise', '/core/attachments/voucher/'.$voucher->id), ['file' => UploadedFile::fake()->image('receipt.jpg')])->assertSessionHasNoErrors();
    get(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}"))->assertOk()->assertSeeHtml('data-attachment')->assertSee('receipt.jpg')->assertSeeHtml('data-voucher-summary');
});

it('lists vouchers with a type filter and shows the forms', function (): void {
    staffUser(DefaultRole::Accountant);
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome());
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense());

    get(tenantUrl('sunrise', '/accounting/vouchers'))->assertOk()->assertSeeHtml('data-new-income')->assertSeeHtml('data-new-expense');
    get(tenantUrl('sunrise', '/accounting/vouchers/data'))->assertOk()->assertJsonPath('recordsFiltered', 2);
    get(tenantUrl('sunrise', '/accounting/vouchers/data?type=income'))->assertOk()->assertJsonPath('recordsFiltered', 1);
    get(tenantUrl('sunrise', '/accounting/vouchers/new/expense'))->assertOk()->assertSeeHtml('data-voucher-form')->assertSee('of which input VAT');
    get(tenantUrl('sunrise', '/accounting/vouchers/new/income'))->assertOk()->assertDontSee('of which input VAT');
    get(tenantUrl('sunrise', '/accounting/vouchers/new/other'))->assertNotFound();
});

it('lets the general manager look but not record or void, and keeps a waiter out', function (): void {
    staffUser(DefaultRole::Accountant);
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcExpense());
    $voucher = vcStored('Generator fuel');

    staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', '/accounting/vouchers'))->assertOk()->assertDontSeeHtml('data-new-income');
    get(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}"))->assertOk()->assertDontSeeHtml('data-void');
    get(tenantUrl('sunrise', '/accounting/vouchers/new/income'))->assertForbidden();
    post(tenantUrl('sunrise', '/accounting/vouchers'), vcIncome())->assertForbidden();
    post(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}/void"), ['reason' => 'x'])->assertForbidden();

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/accounting/vouchers'))->assertForbidden();
    get(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}"))->assertForbidden();
});
