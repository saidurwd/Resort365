<?php

namespace Modules\HR\Services;

use Carbon\CarbonImmutable;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Holiday;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\Shift;

/**
 * The roster screen's data (Step 6.2): a property's working employees (of one department, if asked) by date, with the
 * rostered shift, a day off, or nothing planned in each cell.
 */
class RosterGrid
{
    public function __construct(private readonly ScheduleLookup $schedule) {}

    /**
     * @return array{dates: list<string>, holidays: array<string, Holiday>, employees: list<array{employee: Employee, cells: array<string, array{planned: bool, shift: Shift|null, leave: LeaveType|null}>}>}
     */
    public function build(int $propertyId, ?int $departmentId, string $from, int $days): array
    {
        $start = CarbonImmutable::parse($from);
        $dates = [];

        for ($n = 0; $n < $days; $n++) {
            $dates[] = $start->addDays($n)->toDateString();
        }

        $last = $dates[$days - 1];
        $employees = Employee::query()->with('designation')->where('property_id', $propertyId)->where('status', '!=', EmployeeStatus::Exited->value)->whereDate('joined_on', '<=', $last)
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))->orderBy('first_name')->orderBy('last_name')->get();
        $rosters = $this->schedule->rosters($employees->pluck('id')->all(), $dates[0], $last);
        $leave = $this->schedule->leave($employees->pluck('id')->all(), $dates[0], $last);
        $rows = [];

        foreach ($employees as $employee) {
            $cells = [];

            foreach ($dates as $date) {
                $planned = array_key_exists($date, $rosters[$employee->id] ?? []);
                $cells[$date] = ['planned' => $planned, 'shift' => $rosters[$employee->id][$date] ?? null, 'leave' => $leave[$employee->id][$date] ?? null];
            }

            $rows[] = ['employee' => $employee, 'cells' => $cells];
        }

        return ['dates' => $dates, 'holidays' => $this->schedule->holidays($propertyId, $dates[0], $last), 'employees' => $rows];
    }
}
