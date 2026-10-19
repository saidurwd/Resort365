<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;

/**
 * Puts employees on a shift (or a day off, with no shift) for a range of dates, optionally only on some weekdays
 * (Step 6.2). An existing roster entry for the same day is replaced. Dates before an employee joined or after they left
 * are skipped, and so are days of approved leave (cancel the leave to change them). At most 93 days at a time.
 */
class AssignRoster extends Action
{
    private const int MAX_DAYS = 93;

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  list<int>  $employeeIds
     * @param  list<int>|null  $weekdays  0 = Sunday … 6 = Saturday; null means every day
     * @return int the days written
     *
     * @throws HrRuleViolated
     */
    public function handle(array $employeeIds, string $from, string $to, ?int $shiftId, ?array $weekdays = null): int
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        if ($end->lt($start)) {
            throw new HrRuleViolated(__('The last date is before the first.'));
        }

        if ($start->diffInDays($end) >= self::MAX_DAYS) {
            throw new HrRuleViolated(__('Roster at most :days days at a time.', ['days' => self::MAX_DAYS]));
        }

        if ($shiftId !== null && ! Shift::query()->whereKey($shiftId)->where('is_active', true)->exists()) {
            throw new HrRuleViolated(__('Choose an active shift.'));
        }

        $employees = Employee::query()->whereIn('id', $employeeIds)->get();

        if ($employees->isEmpty()) {
            throw new HrRuleViolated(__('Choose at least one employee.'));
        }

        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $now = now();
        $rows = [];
        $onLeave = Roster::query()->whereIn('employee_id', $employees->pluck('id'))->whereBetween('work_date', [$from, $to])->whereNotNull('leave_request_id')->get()
            ->mapWithKeys(fn (Roster $roster): array => [$roster->employee_id.'|'.$roster->work_date->toDateString() => true]);

        foreach ($employees as $employee) {
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $date = $day->toDateString();

                if (isset($onLeave[$employee->id.'|'.$date]) || ($weekdays !== null && ! in_array($day->dayOfWeek, $weekdays, true)) || $date < $employee->joined_on->toDateString() || ($employee->exited_on !== null && $date > $employee->exited_on->toDateString())) {
                    continue;
                }

                $rows[] = ['tenant_id' => $tenantId, 'property_id' => $employee->property_id, 'employee_id' => $employee->id, 'work_date' => $date, 'shift_id' => $shiftId, 'created_at' => $now, 'updated_at' => $now];
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
