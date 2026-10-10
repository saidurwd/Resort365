<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\BankLedger;
use Modules\Accounting\Services\ChequeTracker;

/**
 * Matches a statement line to a ledger line of the bank's account, one to one and for the same amount (Step 4.4).
 * A cheque on the ledger line's voucher is cleared.
 */
class MatchLines extends Action
{
    public function __construct(private readonly ChequeTracker $cheques) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(BankStatementLine $line, int $journalLineId, ?int $userId = null): BankMatch
    {
        return $this->transaction(function () use ($line, $journalLineId, $userId): BankMatch {
            $statementLine = BankStatementLine::query()->with('statement.reconciliation')->lockForUpdate()->findOrFail($line->id);
            $journalLine = JournalLine::query()->with('entry')->findOrFail($journalLineId);
            $bank = $statementLine->statement->bankAccount;

            if ($statementLine->statement->reconciliation !== null) {
                throw new AccountingRuleViolated(__('This statement is already reconciled.'));
            }

            if (BankMatch::query()->where('bank_statement_line_id', $statementLine->id)->orWhere('journal_line_id', $journalLine->id)->exists()) {
                throw new AccountingRuleViolated(__('One of the two lines is already matched.'));
            }

            if ($journalLine->account_id !== $bank->account_id) {
                throw new AccountingRuleViolated(__('That ledger line belongs to another account.'));
            }

            if (bccomp(BankLedger::signed($journalLine), $statementLine->signedAmount(), 2) !== 0) {
                throw new AccountingRuleViolated(__('The amounts differ: the statement says :bank, the ledger :book.', ['bank' => $statementLine->signedAmount(), 'book' => BankLedger::signed($journalLine)]));
            }

            $match = BankMatch::query()->create(['bank_statement_line_id' => $statementLine->id, 'journal_line_id' => $journalLine->id, 'matched_by' => $userId, 'matched_at' => now()]);
            $this->cheques->cleared($journalLine->journal_entry_id);

            return $match;
        });
    }
}
