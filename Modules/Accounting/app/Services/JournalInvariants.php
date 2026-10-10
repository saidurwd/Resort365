<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;

/**
 * The rules every journal entry must meet before it is posted (ARCHITECTURE §7.2), without the database:
 * at least two lines, each a debit or a credit of more than nothing (never both), and Σ debit = Σ credit.
 * Amounts are decimal strings. Returns the problems in words for the person fixing the entry.
 */
class JournalInvariants
{
    /**
     * @param  list<array{debit: string, credit: string}>  $lines
     * @return list<string> empty when the entry may be posted
     */
    public function problems(array $lines): array
    {
        $problems = [];

        if (count($lines) < 2) {
            return [__('An entry needs at least two lines.')];
        }

        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $d = BigDecimal::of($line['debit'] === '' ? '0' : $line['debit']);
            $c = BigDecimal::of($line['credit'] === '' ? '0' : $line['credit']);

            if ($d->isNegative() || $c->isNegative()) {
                $problems[] = __('Line :n: amounts cannot be negative.', ['n' => $number]);
            } elseif ($d->isPositive() && $c->isPositive()) {
                $problems[] = __('Line :n: use either a debit or a credit, not both.', ['n' => $number]);
            } elseif ($d->isZero() && $c->isZero()) {
                $problems[] = __('Line :n: enter a debit or a credit.', ['n' => $number]);
            }

            $debit = $debit->plus($d);
            $credit = $credit->plus($c);
        }

        if (! $debit->isEqualTo($credit)) {
            $problems[] = __('The entry does not balance: debits :debit, credits :credit.', ['debit' => (string) $debit->toScale(2), 'credit' => (string) $credit->toScale(2)]);
        }

        return $problems;
    }

    /**
     * @param  list<array{debit: string, credit: string}>  $lines
     */
    public function total(array $lines): string
    {
        return (string) array_reduce($lines, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['debit'] === '' ? '0' : $line['debit']), BigDecimal::zero())->toScale(2);
    }
}
