<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PoolParticipant;

/**
 * Sets who shares in the pools and with how many points (Step 6.6). It does not change a pool already worked out: work it out
 * again to use the new points.
 */
class SavePoolParticipants extends Action
{
    public function __construct(private readonly EmployeeDirectory $employees) {}

    /**
     * @param  list<array{employee_id: int, eligible: bool, points: string}>  $rows
     *
     * @throws PayrollRuleViolated
     */
    public function handle(array $rows): int
    {
        foreach ($rows as $row) {
            if (! $this->employees->find($row['employee_id']) instanceof EmployeeSummary) {
                throw new PayrollRuleViolated(__('That employee does not exist, or you cannot see them.'));
            }

            if (! is_numeric($row['points']) || BigDecimal::of($row['points'])->isLessThan(0) || BigDecimal::of($row['points'])->isGreaterThan(1000)) {
                throw new PayrollRuleViolated(__('Points are a number from 0 to 1,000.'));
            }
        }

        return $this->transaction(function () use ($rows): int {
            foreach ($rows as $row) {
                PoolParticipant::query()->updateOrCreate(['employee_id' => $row['employee_id']], ['is_eligible' => $row['eligible'], 'points' => (string) BigDecimal::of($row['points'])->toScale(2)]);
            }

            return count($rows);
        });
    }
}
