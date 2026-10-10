<?php

/*
| Accounting (Step 4.4): cash and bank accounts, transfers, statement import, matching and reconciliation, the
| cheque register. "Done when": a bank statement built from the recorded payments is imported and reconciled.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Accounting\Actions\AcceptSuggestions;
use Modules\Accounting\Actions\CompleteReconciliation;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\CreateTransfer;
use Modules\Accounting\Actions\CreateVoucher;
use Modules\Accounting\Actions\ImportStatement;
use Modules\Accounting\Actions\MarkChequeBounced;
use Modules\Accounting\Actions\MatchLines;
use Modules\Accounting\Actions\SaveBankAccount;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Actions\UnmatchLines;
use Modules\Accounting\Actions\VoidTransfer;
use Modules\Accounting\DTOs\TransferData;
use Modules\Accounting\DTOs\VoucherData;
use Modules\Accounting\Enums\ChequeStatus;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Enums\VoucherType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\FundTransfer;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Models\Voucher;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentMethod;

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
});

function bkAccountId(string $code): int
{
    return booking(fn (): int => Account::query()->where('code', $code)->value('id'));
}

function bkBank(string $name = 'City Bank', string $code = '1120', string $kind = 'bank'): BankAccount
{
    return booking(fn (): BankAccount => app(SaveBankAccount::class)->handle(null, ['account_id' => bkAccountId($code), 'name' => $name, 'kind' => $kind, 'bank_name' => 'City', 'account_number' => '123']));
}

/** A deposit into the bank's ledger account on a day: a posted income voucher. */
function bkDeposit(string $amount, ?string $date = null, ?string $chequeNo = null, string $description = 'Hall rental'): Voucher
{
    return booking(fn (): Voucher => app(CreateVoucher::class)->handle(new VoucherData(VoucherType::Income, bookingIds()['property'], $date ?? now()->toDateString(), bkAccountId('4230'), bkAccountId('1120'), $amount, $description, chequeNo: $chequeNo)));
}

function bkChequeOut(string $amount, string $chequeNo = 'CHQ-1'): Voucher
{
    return booking(fn (): Voucher => app(CreateVoucher::class)->handle(new VoucherData(VoucherType::Expense, bookingIds()['property'], now()->toDateString(), bkAccountId('6130'), bkAccountId('1120'), $amount, 'Supplies', chequeNo: $chequeNo)));
}

/**
 * @param  list<array{string, string, string, string, string}>  $rows  date, description, reference, withdrawal, deposit
 */
function bkCsv(array $rows): string
{
    return "date,description,reference,withdrawal,deposit\n".implode("\n", array_map(fn (array $row): string => implode(',', $row), $rows));
}

/**
 * @return array{statement: BankStatement, imported: int, skipped: int}
 */
function bkImport(BankAccount $bank, string $csv, ?string $closing = null): array
{
    return booking(fn (): array => app(ImportStatement::class)->handle($bank, 'statement.csv', $csv, $closing));
}

function bkToday(): string
{
    return now()->toDateString();
}

it('keeps cash and bank accounts, each tied to one postable asset account', function (): void {
    $bank = bkBank();

    expect($bank->kind->value)->toBe('bank');
    expect(fn () => bkBank('Again'))->toThrow(AccountingRuleViolated::class);
    expect(fn () => booking(fn () => app(SaveBankAccount::class)->handle(null, ['account_id' => bkAccountId('4230'), 'name' => 'Income', 'kind' => 'bank'])))->toThrow(AccountingRuleViolated::class);
    expect(fn () => booking(fn () => app(SaveBankAccount::class)->handle(null, ['account_id' => bkAccountId('1100'), 'name' => 'Group', 'kind' => 'bank'])))->toThrow(AccountingRuleViolated::class);

    $cash = bkBank('Front desk cash', '1110', 'cash');
    expect($cash->bank_name)->toBeNull();
});

it('moves money between accounts and voids the transfer', function (): void {
    $from = bkBank();
    $to = bkBank('Savings', '1110', 'cash');
    $transfer = booking(fn (): FundTransfer => app(CreateTransfer::class)->handle(new TransferData(bookingIds()['property'], bkToday(), $from->id, $to->id, '5000.00', 'TT-1')));

    expect($transfer->transfer_no)->toStartWith('TR-')
        ->and(booking(fn (): array => JournalLine::query()->where('journal_entry_id', $transfer->journal_entry_id)->orderBy('line_no')->get()->map(fn (JournalLine $line): array => [$line->account_id, $line->debit, $line->credit])->all()))
        ->toBe([[bkAccountId('1110'), '5000.00', '0.00'], [bkAccountId('1120'), '0.00', '5000.00']]);

    expect(fn () => booking(fn () => app(CreateTransfer::class)->handle(new TransferData(bookingIds()['property'], bkToday(), $from->id, $from->id, '5.00'))))->toThrow(AccountingRuleViolated::class);
    expect(fn () => booking(fn () => app(CreateTransfer::class)->handle(new TransferData(bookingIds()['property'], bkToday(), $from->id, $to->id, '0'))))->toThrow(AccountingRuleViolated::class);

    booking(fn () => app(VoidTransfer::class)->handle($transfer, 'Wrong account'));
    expect(booking(fn () => $transfer->fresh()->status))->toBe(VoucherStatus::Void);
    expect(fn () => booking(fn () => app(VoidTransfer::class)->handle($transfer->fresh(), 'Again')))->toThrow(AccountingRuleViolated::class);
});

it('imports a statement once, reading the closing balance from the file or the form', function (): void {
    $bank = bkBank();
    $csv = "date,description,reference,withdrawal,deposit,balance\n".bkToday().",Deposit,R1,,1000.00,1000.00\n".bkToday().',Charge,,25.00,,975.00';
    $result = bkImport($bank, $csv);

    expect($result['imported'])->toBe(2)->and($result['skipped'])->toBe(0)->and($result['statement']->closing_balance)->toBe('975.00')->and($result['statement']->statement_to->toDateString())->toBe(bkToday());
    expect(fn () => bkImport($bank, $csv))->toThrow(AccountingRuleViolated::class, 'nothing new');

    $more = $csv."\n".bkToday().',Interest,,,2.00,977.00';
    $second = bkImport($bank, $more);
    expect($second['imported'])->toBe(1)->and($second['skipped'])->toBe(2)->and($second['statement']->closing_balance)->toBe('977.00');

    expect(fn () => bkImport($bank, bkCsv([[bkToday(), 'No balance column', '', '', '10']])))->toThrow(AccountingRuleViolated::class, 'closing balance');
    expect(bkImport($bank, bkCsv([[bkToday(), 'No balance column', '', '', '10']]), '50.00')['statement']->closing_balance)->toBe('50.00');
});

it('keeps two identical lines of one file apart, and refuses a bad file or a cash account', function (): void {
    $bank = bkBank();
    $twice = bkCsv([[bkToday(), 'ATM', '', '100', ''], [bkToday(), 'ATM', '', '100', '']]);

    expect(bkImport($bank, $twice, '0')['imported'])->toBe(2);
    expect(fn () => bkImport($bank, bkCsv([['not a date', 'x', '', '', '1']]), '1'))->toThrow(AccountingRuleViolated::class, 'Line 2');
    $cash = bkBank('Cash', '1110', 'cash');
    expect(fn () => bkImport($cash, bkCsv([[bkToday(), 'x', '', '', '1']]), '1'))->toThrow(AccountingRuleViolated::class);
    expect(booking(fn (): int => BankStatement::query()->count()))->toBe(1);
});

it('matches statement lines to ledger lines of the same amount, one to one', function (): void {
    $bank = bkBank();
    $voucher = bkDeposit('2500.00');
    $statement = bkImport($bank, bkCsv([[bkToday(), 'Cash deposit', '', '', '2500.00'], [bkToday(), 'Other', '', '', '700.00']]), '3200.00')['statement'];
    [$first, $other] = booking(fn () => BankStatementLine::query()->orderBy('line_no')->get()->all());
    $ledgerLine = booking(fn (): JournalLine => JournalLine::query()->where('journal_entry_id', $voucher->journal_entry_id)->where('account_id', bkAccountId('1120'))->sole());

    expect(fn () => booking(fn () => app(MatchLines::class)->handle($other, $ledgerLine->id)))->toThrow(AccountingRuleViolated::class, 'amounts differ');
    $match = booking(fn (): BankMatch => app(MatchLines::class)->handle($first, $ledgerLine->id));
    expect($match->bank_statement_line_id)->toBe($first->id)
        ->and(fn () => booking(fn () => app(MatchLines::class)->handle($first, $ledgerLine->id)))->toThrow(AccountingRuleViolated::class, 'already matched');

    $otherAccountLine = booking(fn (): JournalLine => JournalLine::query()->where('journal_entry_id', $voucher->journal_entry_id)->where('account_id', bkAccountId('4230'))->sole());
    expect(fn () => booking(fn () => app(MatchLines::class)->handle($other, $otherAccountLine->id)))->toThrow(AccountingRuleViolated::class);

    booking(fn () => app(UnmatchLines::class)->handle($match));
    expect(booking(fn (): int => BankMatch::query()->count()))->toBe(0);
});

it('suggests the obvious matches and leaves the doubtful ones', function (): void {
    $bank = bkBank();
    bkDeposit('2500.00', null, null, 'Hall rental');
    bkDeposit('700.00', now()->subDays(20)->toDateString(), null, 'Old deposit');
    $statement = bkImport($bank, bkCsv([[bkToday(), 'Deposit', '', '', '2500.00'], [bkToday(), 'Deposit', '', '', '700.00'], [bkToday(), 'Unknown', '', '33.00', '']]), '3167.00')['statement'];

    expect(booking(fn (): int => app(AcceptSuggestions::class)->handle($statement)))->toBe(1)
        ->and(booking(fn (): int => BankMatch::query()->count()))->toBe(1);
});

it('reconciles a statement built from the recorded payments (done when)', function (): void {
    $bank = bkBank();
    $stay = bookStay(['401'], now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString());
    $payment = booking(fn () => RecordPayment::make()->handle(new NewPayment($stay->id, PaymentMethod::BankTransfer, '3000.00', reference: 'TXN-77')));
    bkDeposit('25000.00');
    $cheque = bkChequeOut('1200.00', 'CHQ-55');

    $statement = bkImport($bank, bkCsv([
        [bkToday(), "Transfer {$payment->receipt_no}", 'TXN-77', '', '3000.00'],
        [bkToday(), 'Event hall deposit', '', '', '25000.00'],
    ]), '28000.00')['statement'];

    expect(booking(fn (): int => app(AcceptSuggestions::class)->handle($statement)))->toBe(2);
    $reconciliation = booking(fn () => app(CompleteReconciliation::class)->handle($statement));

    expect($reconciliation->difference)->toBe('0.00')->and($reconciliation->statement_balance)->toBe('28000.00')->and($reconciliation->book_balance)->toBe('26800.00')->and($reconciliation->outstanding_net)->toBe('-1200.00')
        ->and($cheque->cheque_status)->toBe(ChequeStatus::Pending);
});

it('completes only when every line is matched and the books agree, then locks the matches', function (): void {
    $bank = bkBank();
    $deposit = bkDeposit('2500.00');
    $statement = bkImport($bank, bkCsv([[bkToday(), 'Deposit', '', '', '2500.00'], [bkToday(), 'Charge', '', '15.00', '']]), '2485.00')['statement'];
    booking(fn () => app(AcceptSuggestions::class)->handle($statement));

    expect(fn () => booking(fn () => app(CompleteReconciliation::class)->handle($statement)))->toThrow(AccountingRuleViolated::class, 'not matched');

    // The bank's charge is recorded in the books; then the closing balance is wrong by 40.
    $charge = booking(fn (): Voucher => app(CreateVoucher::class)->handle(new VoucherData(VoucherType::Expense, bookingIds()['property'], bkToday(), bkAccountId('6150'), bkAccountId('1120'), '15.00', 'Bank charge')));
    booking(fn () => app(AcceptSuggestions::class)->handle($statement));
    booking(fn () => $statement->forceFill(['closing_balance' => '2525.00'])->save());
    expect(fn () => booking(fn () => app(CompleteReconciliation::class)->handle($statement->fresh())))->toThrow(AccountingRuleViolated::class, 'differ by 40.00');

    booking(fn () => $statement->forceFill(['closing_balance' => '2485.00'])->save());
    booking(fn () => app(CompleteReconciliation::class)->handle($statement->fresh()));
    $match = booking(fn (): BankMatch => BankMatch::query()->firstOrFail());

    expect($match->bank_reconciliation_id)->not->toBeNull()
        ->and(fn () => booking(fn () => app(UnmatchLines::class)->handle($match)))->toThrow(AccountingRuleViolated::class, 'completed')
        ->and(fn () => booking(fn () => app(CompleteReconciliation::class)->handle($statement->fresh())))->toThrow(AccountingRuleViolated::class, 'already reconciled')
        ->and(fn () => booking(fn () => app(AcceptSuggestions::class)->handle($statement->fresh())))->toThrow(AccountingRuleViolated::class)
        ->and($deposit->id)->toBeInt()->and($charge->id)->toBeInt();
});

it('clears a cheque when its bank line is matched and makes it pending again when unmatched', function (): void {
    $bank = bkBank();
    $cheque = bkChequeOut('1200.00', 'CHQ-9');
    $statement = bkImport($bank, bkCsv([[bkToday(), 'Cheque paid', 'CHQ-9', '1200.00', '']]), '-1200.00')['statement'];
    booking(fn () => app(AcceptSuggestions::class)->handle($statement));

    expect(booking(fn () => $cheque->fresh()->cheque_status))->toBe(ChequeStatus::Cleared);
    booking(fn () => app(UnmatchLines::class)->handle(BankMatch::query()->sole()));
    expect(booking(fn () => $cheque->fresh()->cheque_status))->toBe(ChequeStatus::Pending);
});

it('marks a pending cheque bounced by voiding its voucher, and only a pending one', function (): void {
    $cheque = bkChequeOut('1200.00', 'CHQ-3');
    booking(fn () => app(MarkChequeBounced::class)->handle($cheque, 'Insufficient funds'));
    $bounced = booking(fn () => $cheque->fresh());

    expect($bounced->cheque_status)->toBe(ChequeStatus::Bounced)->and($bounced->status)->toBe(VoucherStatus::Void);
    expect(fn () => booking(fn () => app(MarkChequeBounced::class)->handle($bounced, 'Again')))->toThrow(AccountingRuleViolated::class, 'pending');
});

it('refuses voiding a transfer that a bank statement already matched', function (): void {
    $from = bkBank();
    $to = bkBank('Cash', '1110', 'cash');
    $transfer = booking(fn (): FundTransfer => app(CreateTransfer::class)->handle(new TransferData(bookingIds()['property'], bkToday(), $from->id, $to->id, '800.00')));
    $statement = bkImport($from, bkCsv([[bkToday(), 'Withdrawal', '', '800.00', '']]), '-800.00')['statement'];
    booking(fn () => app(AcceptSuggestions::class)->handle($statement));

    expect(fn () => booking(fn () => app(VoidTransfer::class)->handle($transfer, 'Oops')))->toThrow(AccountingRuleViolated::class, 'matched');
});

it('shows the banking screens to an accountant and keeps others out', function (): void {
    staffUser(DefaultRole::Accountant);
    $bank = bkBank();
    bkDeposit('2500.00');

    get(tenantUrl('sunrise', '/accounting/banks'))->assertOk()->assertSeeHtml('data-bank="'.$bank->id.'"')->assertSee('2,500.00');
    post(tenantUrl('sunrise', '/accounting/banks'), ['account_id' => bkAccountId('1110'), 'name' => 'Till', 'kind' => 'cash'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/accounting/banks'), ['account_id' => bkAccountId('4230'), 'name' => 'Bad', 'kind' => 'bank'])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/accounting/transfers'), ['transfer_date' => bkToday(), 'from_bank_account_id' => $bank->id, 'to_bank_account_id' => booking(fn (): int => BankAccount::query()->where('name', 'Till')->value('id')), 'amount' => '100'])->assertSessionHas('success');
    get(tenantUrl('sunrise', '/accounting/transfers'))->assertOk()->assertSeeHtml('data-new-transfer');
    get(tenantUrl('sunrise', '/accounting/transfers/data'))->assertOk()->assertJsonPath('recordsFiltered', 1);
    get(tenantUrl('sunrise', '/accounting/transfers/new'))->assertOk()->assertSeeHtml('data-transfer-form');
    get(tenantUrl('sunrise', '/accounting/cheques'))->assertOk();
    get(tenantUrl('sunrise', '/accounting/reconciliation'))->assertOk();

    staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', '/accounting/banks'))->assertOk();
    get(tenantUrl('sunrise', '/accounting/transfers/new'))->assertForbidden();
    post(tenantUrl('sunrise', '/accounting/banks'), ['account_id' => bkAccountId('1110'), 'name' => 'X', 'kind' => 'cash'])->assertForbidden();

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/accounting/banks'))->assertForbidden();
    get(tenantUrl('sunrise', '/accounting/reconciliation'))->assertForbidden();
});

it('imports a file through the screen, matches on the reconciliation page and exports the report', function (): void {
    staffUser(DefaultRole::Accountant);
    $bank = bkBank();
    bkDeposit('2500.00');
    $file = UploadedFile::fake()->createWithContent('stmt.csv', bkCsv([[bkToday(), 'Cash deposit', '', '', '2500.00']]));

    post(tenantUrl('sunrise', '/accounting/reconciliation/statements'), ['bank_account_id' => $bank->id, 'file' => $file, 'closing_balance' => '2500.00'])->assertSessionHas('success');
    $statement = booking(fn (): BankStatement => BankStatement::query()->sole());

    get(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}"))->assertOk()->assertSeeHtml('data-match-form')->assertSeeHtml('data-suggest');
    post(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}/suggest"))->assertSessionHas('success');
    get(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}"))->assertOk()->assertSeeHtml('data-unmatch');
    post(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}/complete"))->assertSessionHas('success');
    get(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}"))->assertOk()->assertSeeHtml('data-reconciled');
    get(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}/report"))->assertOk()->assertSeeHtml('data-figures')->assertSeeHtml('data-export');
    get(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}/report?export=csv"))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertSeeHtml('Reconciliation report');

    post(tenantUrl('sunrise', '/accounting/reconciliation/statements'), ['bank_account_id' => $bank->id, 'file' => UploadedFile::fake()->createWithContent('bad.csv', "a,b\n1,2")])->assertSessionHas('error');
});

it('lets a manual match through the screen and refuses what does not fit', function (): void {
    staffUser(DefaultRole::Accountant);
    $bank = bkBank();
    $voucher = bkDeposit('2500.00');
    $statement = bkImport($bank, bkCsv([[bkToday(), 'Deposit', '', '', '2500.00'], [bkToday(), 'Other', '', '', '9.00']]), '2509.00')['statement'];
    [$first, $second] = booking(fn () => BankStatementLine::query()->orderBy('line_no')->get()->all());
    $ledger = booking(fn (): int => JournalLine::query()->where('journal_entry_id', $voucher->journal_entry_id)->where('account_id', bkAccountId('1120'))->value('id'));

    post(tenantUrl('sunrise', '/accounting/reconciliation/matches'), ['statement_line_id' => $second->id, 'journal_line_id' => $ledger])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/accounting/reconciliation/matches'), ['statement_line_id' => $first->id, 'journal_line_id' => $ledger])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/accounting/reconciliation/matches'), ['statement_line_id' => 999999, 'journal_line_id' => $ledger])->assertSessionHasErrors('statement_line_id');
    post(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}/complete"))->assertSessionHas('error');

    staffUser(DefaultRole::GeneralManager);
    post(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}/suggest"))->assertForbidden();
    get(tenantUrl('sunrise', "/accounting/reconciliation/statements/{$statement->id}"))->assertOk()->assertDontSeeHtml('data-suggest');
});

it('records a cheque on a voucher through the form and lists it in the register', function (): void {
    staffUser(DefaultRole::Accountant);
    bkBank();

    get(tenantUrl('sunrise', '/accounting/vouchers/new/expense'))->assertOk()->assertSee('Cheque issued, number');
    post(tenantUrl('sunrise', '/accounting/vouchers'), ['type' => 'expense', 'voucher_date' => bkToday(), 'account_id' => bkAccountId('6130'), 'cash_account_id' => bkAccountId('1120'), 'amount' => '900', 'description' => 'Paint', 'cheque_no' => 'CHQ-12'])->assertSessionHas('success');
    $voucher = booking(fn (): Voucher => Voucher::query()->where('description', 'Paint')->sole());

    expect($voucher->cheque_status)->toBe(ChequeStatus::Pending)->and($voucher->cheque_date?->toDateString())->toBe(bkToday());
    get(tenantUrl('sunrise', '/accounting/cheques/data'))->assertOk()->assertJsonPath('recordsFiltered', 1);
    get(tenantUrl('sunrise', '/accounting/cheques/data?status=cleared'))->assertOk()->assertJsonPath('recordsFiltered', 0);
    get(tenantUrl('sunrise', "/accounting/vouchers/{$voucher->id}"))->assertOk()->assertSeeHtml('data-cheque')->assertSeeHtml('data-bounce');

    post(tenantUrl('sunrise', "/accounting/cheques/{$voucher->id}/bounce"), [])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', "/accounting/cheques/{$voucher->id}/bounce"), ['reason' => 'Account closed'])->assertSessionHas('success');
    expect(booking(fn () => $voucher->fresh()->cheque_status))->toBe(ChequeStatus::Bounced);
});
