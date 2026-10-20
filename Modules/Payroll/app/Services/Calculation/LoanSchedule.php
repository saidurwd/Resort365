<?php

namespace Modules\Payroll\Services\Calculation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * The installments of a loan or advance (Step 6.5). Pure: equal amounts to the cent, the last one taking the rounding, one
 * per month from the first month.
 */
final class LoanSchedule
{
    /**
     * @return list<array{sequence: int, due_month: string, amount: string}>
     */
    public static function build(string $principal, int $count, string $firstMonth): array
    {
        $total = BigDecimal::of($principal)->toScale(2, RoundingMode::HalfUp);
        $each = $total->dividedBy($count, 2, RoundingMode::Down);
        $start = CarbonImmutable::parse($firstMonth)->startOfMonth();
        $rows = [];
        $paid = BigDecimal::zero();

        for ($n = 1; $n <= $count; $n++) {
            $amount = $n === $count ? $total->minus($paid) : $each;
            $paid = $paid->plus($amount);
            $rows[] = ['sequence' => $n, 'due_month' => $start->addMonthsNoOverflow($n - 1)->toDateString(), 'amount' => (string) $amount->toScale(2)];
        }

        return $rows;
    }
}
