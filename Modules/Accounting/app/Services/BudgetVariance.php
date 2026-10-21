<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Budget against actual (Step 7.2). Pure.
 *
 * The variance is favourable when it is positive: for income the actual less the budget (more than planned), for expense the
 * budget less the actual (less than planned). The percent is the variance of the budget, or null when nothing was budgeted.
 */
final class BudgetVariance
{
    /**
     * @param  string  $type  "income" or "expense"
     * @return array{amount: string, percent: string|null}
     */
    public function of(string $type, string $budget, string $actual): array
    {
        $amount = $type === 'income' ? BigDecimal::of($actual)->minus($budget) : BigDecimal::of($budget)->minus($actual);
        $percent = BigDecimal::of($budget)->isZero() ? null : (string) $amount->multipliedBy(100)->dividedBy(BigDecimal::of($budget)->abs(), 1, RoundingMode::HalfUp);

        return ['amount' => (string) $amount->toScale(2), 'percent' => $percent];
    }

    /**
     * The sum of the months from `$first` to `$last` of a line's twelve amounts (month 1 is the first index).
     *
     * @param  array<int, string>  $months
     */
    public function sum(array $months, int $first, int $last): string
    {
        $sum = BigDecimal::zero();

        for ($month = $first; $month <= $last; $month++) {
            $sum = $sum->plus($months[$month] ?? '0');
        }

        return (string) $sum->toScale(2);
    }
}
