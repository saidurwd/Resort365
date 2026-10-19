<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Roster;

/**
 * Repeats one week of the roster over the following weeks (Step 6.2), for employees who have a roster in that week.
 * Days off are copied too; dates outside an employee's employment are skipped. At most 12 weeks.
 */
class CopyRosterWeek extends Action
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  list<int>  $employeeIds
     * @return int the days written
     *
     * @throws HrRuleViolated
     */
    public function handle(array $employeeIds, string $weekStart, int $weeks): int
    {
        if ($weeks < 1 || $weeks > 12) {
            throw new HrRuleViolated(__('Copy between 1 and 12 weeks.'));
        }

        $start = CarbonImmutable::parse($weekStart);
        $source = Roster::query()->whereIn('employee_id', $employeeIds)->whereBetween('work_date', [$start->toDateString(), $start->addDays(6)->toDateString()])->get();

        if ($source->isEmpty()) {
            throw new HrRuleViolated(__('There is no roster in the week starting :date to copy.', ['date' => $start->format('d M Y')]));
        }

        $employees = Employee::query()->whereIn('id', $source->pluck('employee_id')->unique()->all())->get()->keyBy('id');
        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $now = now();
        $rows = [];

        for ($week = 1; $week <= $weeks; $week++) {
            foreach ($source as $entry) {
                $employee = $employees[$entry->employee_id] ?? null;
                $date = $entry->work_date->addWeeks($week)->toDateString();

                if ($employee === null || $date < $employee->joined_on->toDateString() || ($employee->exited_on !== null && $date > $employee->exited_on->toDateString())) {
                    continue;
                }

                $rows[] = ['tenant_id' => $tenantId, 'property_id' => $employee->property_id, 'employee_id' => $employee->id, 'work_date' => $date, 'shift_id' => $entry->shift_id, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        return $this->transaction(function () use ($rows): int {
            foreach (array_chunk($rows, 500) as $chunk) {
                Roster::query()->upsert($chunk, ['employee_id', 'work_date'], ['shift_id', 'updated_at']);
            }

            return count($rows);
        });
    }
}
