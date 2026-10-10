<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;

/**
 * The reconciliation of a statement (Step 4.4). The bank's closing balance plus the ledger items the bank has
 * not seen yet (deposits in transit, less payments outstanding) must equal the ledger balance plus the bank
 * items the books do not have yet (charges, interest). Pure; amounts are decimal strings, deposits positive.
 */
class ReconciliationCalculator
{
    /**
     * @param  list<string>  $unmatchedLedger  signed amounts of ledger lines not on the statement (debit minus credit)
     * @param  list<string>  $unmatchedStatement  signed amounts of statement lines not in the ledger (deposit minus withdrawal)
     * @return array{statement_balance: string, deposits_in_transit: string, outstanding_payments: string, adjusted_bank: string, book_balance: string, bank_items_not_in_books: string, adjusted_book: string, outstanding_net: string, difference: string}
     */
    public function calculate(string $statementBalance, string $ledgerBalance, array $unmatchedLedger, array $unmatchedStatement): array
    {
        $transit = BigDecimal::zero();
        $outstanding = BigDecimal::zero();

        foreach ($unmatchedLedger as $amount) {
            $value = BigDecimal::of($amount);
            $value->isPositive() ? $transit = $transit->plus($value) : $outstanding = $outstanding->plus($value->abs());
        }

        $notInBooks = array_reduce($unmatchedStatement, fn (BigDecimal $sum, string $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero());
        $adjustedBank = BigDecimal::of($statementBalance)->plus($transit)->minus($outstanding);
        $adjustedBook = BigDecimal::of($ledgerBalance)->plus($notInBooks);
        $scale = fn (BigDecimal $value): string => (string) $value->toScale(2);

        return [
            'statement_balance' => $scale(BigDecimal::of($statementBalance)), 'deposits_in_transit' => $scale($transit), 'outstanding_payments' => $scale($outstanding),
            'adjusted_bank' => $scale($adjustedBank), 'book_balance' => $scale(BigDecimal::of($ledgerBalance)), 'bank_items_not_in_books' => $scale($notInBooks),
            'adjusted_book' => $scale($adjustedBook), 'outstanding_net' => $scale($transit->minus($outstanding)), 'difference' => $scale($adjustedBank->minus($adjustedBook)),
        ];
    }
}
