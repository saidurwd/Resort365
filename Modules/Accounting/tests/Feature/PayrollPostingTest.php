<?php

/*
| Accounting (Step 6.5), payroll postings. "Done when": a demo month's payroll is calculated, approved, paid and posted: the
| approval books the cost and the liabilities, the payment clears the salaries payable, a loan books a receivable, and every
| entry balances and is posted once.
*/

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Actions\SaveJournalDraft;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\LedgerPoolCollections;
use Modules\Payroll\Actions\FinalisePool;
use Modules\Payroll\Actions\PayPayrollRun;
use Modules\Payroll\Actions\PreparePool;
use Modules\Payroll\Actions\WaiveInstallment;
use Modules\Payroll\Contracts\PoolCollections;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Models\LoanInstallment;
use Modules\Payroll\Models\ServiceChargePool;

require_once __DIR__.'/../../../Payroll/tests/Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    prPrepare();
    booking(function (): void {
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle('2026-01-01');
    });
});

/**
 * An entry's lines as "system key => net debit".
 *
 * @return array<string, string>
 */
function payEntry(string $type, int $id, string $event): array
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

it('posts an approved run: the cost on one side, the net pay, tax, provident fund and the loan recovery on the other', function (): void {
    [$rahim] = prMarch();
    prLoan($rahim, '6000', 3);
    $run = prApprove(prRun());
    $lines = payEntry('payroll_run', $run->id, 'approved');
    $entry = booking(fn (): JournalEntry => JournalEntry::query()->where('source_type', 'payroll_run')->where('source_id', $run->id)->firstOrFail());

    expect(round(array_sum(array_map(floatval(...), $lines)), 2))->toBe(0.0)
        ->and($lines['salaries_payable'])->toBe('-'.$run->net_total)
        ->and($lines['tax_payable'])->toBe('-'.$run->tax_total)
        ->and($lines['loan_receivable'])->toBe('-2000.00')
        ->and((float) $lines['salaries_expense'])->toBeGreaterThan(0)
        ->and((float) $lines['pf_payable'])->toBeLessThan(0)
        ->and($entry->entry_date->toDateString())->toBe('2026-03-31');
});

it('posts the payment of a run, clearing the salaries payable', function (): void {
    prMarch();
    $run = prApprove(prRun());
    booking(fn () => app(PayPayrollRun::class)->handle(prFresh($run), PayMethod::BankTransfer, now()->toDateString(), 'BEFTN-1', 1));
    $lines = payEntry('payroll_run', $run->id, 'paid');

    expect($lines['salaries_payable'])->toBe($run->net_total)->and(round(array_sum(array_map(floatval(...), $lines)), 2))->toBe(0.0)->and(count($lines))->toBe(2);
});

it('books the money lent as a receivable, and a written-off installment as an expense', function (): void {
    [$rahim] = prMarch();
    $loan = prLoan($rahim, '6000', 3);

    expect(payEntry('payroll_loan', $loan->id, 'disbursed'))->toMatchArray(['loan_receivable' => '6000.00']);

    $installment = booking(fn (): LoanInstallment => LoanInstallment::query()->where('loan_id', $loan->id)->where('sequence', 1)->firstOrFail());
    booking(fn () => app(WaiveInstallment::class)->handle($installment, 'Hardship'));
    $waived = payEntry('payroll_installment', $installment->id, 'waived');

    expect($waived)->toMatchArray(['benefits_expense' => '2000.00', 'loan_receivable' => '-2000.00']);
});

it('posts once however often history is posted again', function (): void {
    [$rahim] = prMarch();
    prLoan($rahim, '6000', 3);
    $run = prApprove(prRun());
    booking(fn () => app(PayPayrollRun::class)->handle(prFresh($run), PayMethod::Cash, now()->toDateString(), null, 1));
    $count = fn (): int => booking(fn (): int => JournalEntry::query()->whereIn('source_type', ['payroll_run', 'payroll_loan'])->count());
    $before = $count();

    booking(fn () => app(BackpostHistory::class)->handle());
    booking(fn () => app(BackpostHistory::class)->handle());

    expect($before)->toBe(3)->and($count())->toBe(3);
});

/** Books service charge or tips collected (credit) or taken back (debit) against cash, at the property, on a date. */
function payCollect(string $key, string $date, string $amount, bool $credit = true, ?int $propertyId = null): void
{
    booking(function () use ($key, $date, $amount, $credit, $propertyId): void {
        $property = $propertyId ?? bookingIds()['property'];
        $account = fn (string $systemKey): int => Account::query()->where('system_key', $systemKey)->value('id');
        $draft = app(SaveJournalDraft::class)->handle(null, ['entry_date' => $date, 'description' => 'Collected for staff'], [
            ['account_id' => $account('cash_on_hand'), $credit ? 'debit' : 'credit' => $amount, 'property_id' => $property],
            ['account_id' => $account($key), $credit ? 'credit' : 'debit' => $amount, 'property_id' => $property],
        ]);
        app(PostJournalEntry::class)->handle($draft);
    });
}

/** The ledger balance (credit less debit) of an account over the year. */
function payBalance(string $key): string
{
    return booking(function () use ($key): string {
        $id = Account::query()->where('system_key', $key)->value('id');
        $lines = JournalLine::query()->where('account_id', $id);

        return bcsub((string) (clone $lines)->sum('credit'), (string) (clone $lines)->sum('debit'), 2);
    });
}

it('reads what was collected for staff from the ledger: the credits of the month at the property, less what was taken back', function (): void {
    payCollect('service_charge_payable', '2026-03-05', '100000.00');
    payCollect('service_charge_payable', '2026-03-20', '5000.00', credit: false);
    payCollect('service_charge_payable', '2026-04-02', '7000.00');
    payCollect('tips_payable', '2026-03-12', '20000.00');
    $collections = booking(fn (): PoolCollections => app(PoolCollections::class));
    $property = bookingIds()['property'];
    $collected = fn (int $propertyId, string $from, string $to, PoolKind $kind): string => booking(fn (): string => $collections->collected($propertyId, $from, $to, $kind));

    expect($collections)->toBeInstanceOf(LedgerPoolCollections::class)
        ->and($collected($property, '2026-03-01', '2026-03-31', PoolKind::ServiceCharge))->toBe('95000.00')
        ->and($collected($property, '2026-03-01', '2026-03-31', PoolKind::Tips))->toBe('20000.00')
        ->and($collected($property, '2026-05-01', '2026-05-31', PoolKind::Tips))->toBe('0.00')
        ->and($collected($property + 1, '2026-03-01', '2026-03-31', PoolKind::Tips))->toBe('0.00');
});

it('clears the service charge and tips liabilities when the run that distributes them is approved (done when)', function (): void {
    prMarch();
    payCollect('service_charge_payable', '2026-03-05', '100000.00');
    payCollect('tips_payable', '2026-03-12', '20000.00');

    foreach ([PoolKind::ServiceCharge, PoolKind::Tips] as $kind) {
        $pool = booking(fn (): ServiceChargePool => app(PreparePool::class)->handle(bookingIds()['property'], '2026-03', $kind, PoolRule::Equal, null, 1));
        booking(fn () => app(FinalisePool::class)->handle($pool, 1));
    }

    expect(payBalance('service_charge_payable'))->toBe('100000.00')->and(payBalance('tips_payable'))->toBe('20000.00');

    $run = prApprove(prRun());
    $lines = payEntry('payroll_run', $run->id, 'approved');

    // The shares debit the liabilities instead of the expense, so the entry still balances and the liabilities are cleared.
    expect($lines['service_charge_payable'])->toBe('100000.00')->and($lines['tips_payable'])->toBe('20000.00')
        ->and(round(array_sum(array_map(floatval(...), $lines)), 2))->toBe(0.0)->and($lines['salaries_payable'])->toBe('-'.$run->net_total)
        ->and(payBalance('service_charge_payable'))->toBe('0.00')->and(payBalance('tips_payable'))->toBe('0.00');
});
