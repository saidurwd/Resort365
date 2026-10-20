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
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Payroll\Actions\PayPayrollRun;
use Modules\Payroll\Actions\WaiveInstallment;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Models\LoanInstallment;

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
