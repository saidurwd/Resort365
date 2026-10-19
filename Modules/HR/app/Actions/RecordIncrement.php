<?php

namespace Modules\HR\Actions;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;

/**
 * Records a pay increment in the employee's history (Step 6.1). It is a record only: the salary itself, and the payroll
 * that uses it, come with Step 6.4 (TODO(step-6.4): apply the new amount to the employee's salary).
 */
class RecordIncrement extends LifecycleAction
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, string $previousAmount, string $newAmount, string $effectiveOn, ?string $notes, ?int $userId): Employee
    {
        $this->assertCurrent($employee);

        if (! is_numeric($previousAmount) || ! is_numeric($newAmount) || BigDecimal::of($previousAmount)->isNegative() || ! BigDecimal::of($newAmount)->isPositive()) {
            throw new HrRuleViolated(__('Enter the amounts before and after the increment.'));
        }

        $previous = BigDecimal::of($previousAmount)->toScale(2, RoundingMode::HalfUp);
        $new = BigDecimal::of($newAmount)->toScale(2, RoundingMode::HalfUp);

        if ($new->isLessThanOrEqualTo($previous)) {
            throw new HrRuleViolated(__('An increment must raise the amount.'));
        }

        return $this->transaction(function () use ($employee, $previous, $new, $effectiveOn, $notes, $userId): Employee {
            $this->history->record($employee, HistoryEvent::Increment, $effectiveOn, ['previous_amount' => (string) $previous, 'new_amount' => (string) $new], $notes, $userId);

            return $employee;
        });
    }
}
