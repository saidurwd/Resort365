<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\AcceptSuggestions;
use Modules\Accounting\Actions\CompleteReconciliation;
use Modules\Accounting\Actions\ImportStatement;
use Modules\Accounting\Actions\MatchLines;
use Modules\Accounting\Actions\UnmatchLines;
use Modules\Accounting\Enums\BankAccountKind;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\MatchRequest;
use Modules\Accounting\Http\Requests\StatementImportRequest;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\BankLedger;
use Modules\Accounting\Services\ReconciliationView;

/**
 * Accounting → Reconciliation: import a bank statement, match its lines to the books and complete the
 * reconciliation; the report shows the figures.
 */
class ReconciliationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', BankAccount::class);

        return view('accounting::banking.reconciliation.index', [
            'statements' => BankStatement::query()->with(['bankAccount', 'reconciliation'])->orderByDesc('statement_to')->orderByDesc('id')->get(),
            'banks' => BankAccount::query()->where('kind', BankAccountKind::Bank->value)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'canManage' => auth()->user()?->can('accounting.bank.manage') ?? false,
        ]);
    }

    public function import(StatementImportRequest $request, ImportStatement $import): RedirectResponse
    {
        Gate::authorize('create', BankAccount::class);
        $file = $request->file('file');
        $bank = BankAccount::query()->findOrFail((int) $request->validated('bank_account_id'));

        try {
            $result = $import->handle($bank, (string) $file?->getClientOriginalName(), (string) file_get_contents((string) $file?->getRealPath()), $request->filled('closing_balance') ? (string) $request->validated('closing_balance') : null, $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.reconciliation.show', $result['statement'])->with('success', trans_choice(':count line imported.|:count lines imported.', $result['imported']).($result['skipped'] > 0 ? ' '.trans_choice(':count line was imported before and skipped.|:count lines were imported before and skipped.', $result['skipped']) : ''));
    }

    public function show(BankStatement $statement, ReconciliationView $view): View
    {
        Gate::authorize('view', BankAccount::class);
        $data = $view->for($statement);

        $candidates = [];

        foreach ($data['lines'] as $line) {
            if ($line->match === null) {
                $candidates[$line->id] = array_values(array_filter($data['open'], fn (JournalLine $open): bool => bccomp(BankLedger::signed($open), $line->signedAmount(), 2) === 0));
            }
        }

        return view('accounting::banking.reconciliation.show', [
            'statement' => $statement, ...$data, 'candidates' => $candidates, 'canReconcile' => auth()->user()?->can('accounting.bank.reconcile') ?? false,
        ]);
    }

    public function match(MatchRequest $request, MatchLines $match): RedirectResponse
    {
        Gate::authorize('reconcile', BankAccount::class);
        $line = BankStatementLine::query()->findOrFail((int) $request->validated('statement_line_id'));

        try {
            $match->handle($line, (int) $request->validated('journal_line_id'), $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.reconciliation.show', $line->bank_statement_id)->with('success', __('Lines matched.'));
    }

    public function unmatch(BankMatch $match, UnmatchLines $unmatch): RedirectResponse
    {
        Gate::authorize('reconcile', BankAccount::class);
        $statementId = $match->statementLine->bank_statement_id;

        try {
            $unmatch->handle($match);
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.reconciliation.show', $statementId)->with('success', __('Match removed.'));
    }

    public function suggest(BankStatement $statement, AcceptSuggestions $accept): RedirectResponse
    {
        Gate::authorize('reconcile', BankAccount::class);

        try {
            $count = $accept->handle($statement, auth()->id());
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.reconciliation.show', $statement)->with('success', trans_choice(':count line matched.|:count lines matched.', $count));
    }

    public function complete(BankStatement $statement, CompleteReconciliation $complete): RedirectResponse
    {
        Gate::authorize('reconcile', BankAccount::class);

        try {
            $complete->handle($statement, auth()->id());
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.reconciliation.show', $statement)->with('success', __('Reconciliation completed.'));
    }

    public function report(BankStatement $statement, ReconciliationView $view): View|Response
    {
        Gate::authorize('view', BankAccount::class);
        $data = $view->for($statement);

        if (request()->query('export') === 'csv') {
            $rows = [[__('Reconciliation report'), $statement->bankAccount->name, $statement->statement_to->toDateString()]];
            $rows[] = [__('Statement balance'), $data['figures']['statement_balance']];
            $rows[] = [__('Deposits in transit'), $data['figures']['deposits_in_transit']];
            $rows[] = [__('Payments outstanding'), $data['figures']['outstanding_payments']];
            $rows[] = [__('Adjusted bank balance'), $data['figures']['adjusted_bank']];
            $rows[] = [__('Ledger balance'), $data['figures']['book_balance']];
            $rows[] = [__('Bank items not in the books'), $data['figures']['bank_items_not_in_books']];
            $rows[] = [__('Adjusted book balance'), $data['figures']['adjusted_book']];
            $rows[] = [__('Difference'), $data['figures']['difference']];
            $rows[] = [];
            $rows[] = [__('Date'), __('Description'), __('Reference'), __('Withdrawal'), __('Deposit'), __('Matched to')];

            foreach ($data['lines'] as $line) {
                $rows[] = [$line->txn_date->toDateString(), $line->description, (string) $line->reference, $line->withdrawal, $line->deposit, $line->match?->journalLine->entry->entry_no ?? ''];
            }

            $csv = implode("\n", array_map(fn (array $row): string => implode(',', array_map(fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"', $row)), $rows));

            return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="reconciliation-'.$statement->statement_to->toDateString().'.csv"']);
        }

        return view('accounting::banking.reconciliation.report', ['statement' => $statement, ...$data]);
    }
}
