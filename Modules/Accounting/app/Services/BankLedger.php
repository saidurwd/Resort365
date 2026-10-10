<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\JournalLine;

/**
 * The books' side of a bank account (Step 4.4): its ledger account's balance on a date and the posted lines
 * not matched to a statement yet. A reversed entry's lines count too, its reversal being a line of its own.
 */
class BankLedger
{
    /**
     * The ledger account's balance (debits less credits) of posted entries up to the date.
     */
    public function balance(BankAccount $bank, string $date): string
    {
        $lines = $this->postedLines($bank, $date)->get(['debit', 'credit']);

        return (string) $lines->reduce(fn (BigDecimal $sum, JournalLine $line): BigDecimal => $sum->plus($line->debit)->minus($line->credit), BigDecimal::zero())->toScale(2);
    }

    /**
     * Posted lines up to the date that no statement line is matched to.
     *
     * @return list<JournalLine>
     */
    public function unmatched(BankAccount $bank, string $date): array
    {
        return $this->postedLines($bank, $date)->whereNotIn('id', fn ($query) => $query->select('journal_line_id')->from('bank_matches'))
            ->with('entry')->orderBy('journal_entry_id')->orderBy('line_no')->get()->all();
    }

    public static function signed(JournalLine $line): string
    {
        return (string) BigDecimal::of($line->debit)->minus($line->credit)->toScale(2);
    }

    /**
     * @return Builder<JournalLine>
     */
    private function postedLines(BankAccount $bank, string $date): Builder
    {
        return JournalLine::query()->where('account_id', $bank->account_id)
            ->whereHas('entry', fn ($query) => $query->whereIn('status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])->where('entry_date', '<=', $date));
    }
}
