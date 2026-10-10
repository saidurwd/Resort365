<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\BankLedger;
use Modules\Accounting\Services\StatementMatcher;
use Modules\Core\Contracts\Settings;

/**
 * Matches every statement line the matcher is sure of: same amount, near date, each line once.
 */
class AcceptSuggestions extends Action
{
    public function __construct(
        private readonly StatementMatcher $matcher,
        private readonly BankLedger $ledger,
        private readonly MatchLines $match,
        private readonly Settings $settings,
    ) {}

    /**
     * @return int the number of lines matched
     *
     * @throws AccountingRuleViolated
     */
    public function handle(BankStatement $statement, ?int $userId = null): int
    {
        $statement->load(['bankAccount', 'reconciliation']);

        if ($statement->reconciliation !== null) {
            throw new AccountingRuleViolated(__('This statement is already reconciled.'));
        }

        $lines = BankStatementLine::query()->where('bank_statement_id', $statement->id)->whereDoesntHave('match')->get();
        $ledger = $this->ledger->unmatched($statement->bankAccount, $statement->statement_to->addDays((int) $this->settings->get('accounting.reconcile_date_window'))->toDateString());
        $pairs = $this->matcher->suggest(
            $lines->map(fn (BankStatementLine $line): array => ['id' => $line->id, 'date' => $line->txn_date->toDateString(), 'amount' => $line->signedAmount(), 'reference' => $line->reference])->all(),
            array_map(fn (JournalLine $line): array => ['id' => $line->id, 'date' => $line->entry->entry_date->toDateString(), 'amount' => BankLedger::signed($line), 'text' => trim($line->entry->reference.' '.$line->entry->description.' '.$line->description)], $ledger),
            (int) $this->settings->get('accounting.reconcile_date_window'),
        );

        foreach ($pairs as $pair) {
            $this->match->handle($lines->firstWhere('id', $pair['statement']) ?? throw new AccountingRuleViolated(__('A statement line disappeared.')), $pair['ledger'], $userId);
        }

        return count($pairs);
    }
}
