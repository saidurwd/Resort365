<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveType;

/**
 * Adds or takes away leave days by hand (Step 6.3), with a reason that is kept in the audit log. A balance cannot be
 * taken below what is already used or pending.
 */
class AdjustLeaveBalance extends Action
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, LeaveType $type, int $year, string $days, string $reason, ?int $userId): LeaveBalance
    {
        if (! is_numeric($days) || BigDecimal::of($days)->isZero()) {
            throw new HrRuleViolated(__('Enter the number of days to add (or take away, with a minus).'));
        }

        if (trim($reason) === '') {
            throw new HrRuleViolated(__('Say why the balance is changed.'));
        }

        if (! $type->has_balance) {
            throw new HrRuleViolated(__('":name" has no balance.', ['name' => $type->name]));
        }

        return $this->transaction(function () use ($employee, $type, $year, $days, $reason, $userId): LeaveBalance {
            $balance = LeaveBalance::query()->lockForUpdate()->firstOrNew(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year]);
            $adjusted = BigDecimal::of((string) ($balance->adjusted ?? '0'))->plus($days)->toScale(1, RoundingMode::HalfUp);
            $balance->adjusted = (string) $adjusted;

            if (BigDecimal::of($balance->available())->isNegative()) {
                throw new HrRuleViolated(__('That would leave :name with less than the days already used or waiting for approval.', ['name' => $employee->full_name]));
            }

            $balance->save();
            activity()->performedOn($balance)->withProperties(['days' => $days, 'reason' => $reason, 'year' => $year, 'by' => $userId])->log('leave balance adjusted');

            return $balance;
        });
    }
}
