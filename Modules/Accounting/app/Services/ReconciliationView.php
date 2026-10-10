<?php

namespace Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Accounting\Models\BankMatch;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Models\JournalLine;

/**
 * Everything the reconciliation screen and report show for a statement (Step 4.4): its lines with their
 * matches, the ledger lines still open up to the statement date, and the reconciliation figures. For a completed
 * reconciliation the figures are the ones frozen at completion.
 */
class ReconciliationView
{
    public function __construct(
        private readonly BankLedger $ledger,
        private readonly ReconciliationCalculator $calculator,
    ) {}

    /**
     * @return array{lines: Collection<int, BankStatementLine>, open: list<JournalLine>, figures: array<string, string>, complete: bool, canComplete: bool}
     */
    public function for(BankStatement $statement): array
    {
        $statement->load(['bankAccount', 'reconciliation']);
        $date = $statement->statement_to->toDateString();
        $lines = BankStatementLine::query()->where('bank_statement_id', $statement->id)->with('match.journalLine.entry')->orderBy('line_no')->get();
        $open = $this->ledger->unmatched($statement->bankAccount, $date);
        $unmatchedStatement = $lines->filter(fn (BankStatementLine $line): bool => $line->match === null)->map(fn (BankStatementLine $line): string => $line->signedAmount())->values()->all();
        $figures = $this->calculator->calculate((string) $statement->closing_balance, $this->ledger->balance($statement->bankAccount, $date), array_map(BankLedger::signed(...), $open), $unmatchedStatement);

        return [
            'lines' => $lines, 'open' => $open, 'figures' => $figures, 'complete' => $statement->reconciliation !== null,
            'canComplete' => $statement->reconciliation === null && $unmatchedStatement === [] && $figures['difference'] === '0.00',
        ];
    }

    /**
     * @param  Collection<int, BankStatementLine>  $lines
     * @return list<BankMatch>
     */
    public function matches(Collection $lines): array
    {
        return $lines->map(fn (BankStatementLine $line): ?BankMatch => $line->match)->filter()->values()->all();
    }
}
