<?php

namespace Modules\HR\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Http\Controllers\Concerns\HrScreen;
use Modules\HR\Http\Requests\AttendanceReportRequest;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Services\ScheduleLookup;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * HR → Leave calendar: a month of the current property's employees by day with approved and waiting leave.
 */
class LeaveCalendarController extends Controller
{
    use HrScreen;

    public function show(AttendanceReportRequest $request, DepartmentDirectory $departments, ScheduleLookup $schedule): View
    {
        Gate::authorize('viewAny', LeaveRequest::class);
        $month = CarbonImmutable::parse(($request->validated('month', substr($this->today(), 0, 7))).'-01');
        $from = $month->toDateString();
        $to = $month->endOfMonth()->toDateString();
        $department = $request->filled('department') ? (int) $request->validated('department') : null;
        $propertyId = $this->propertyId();
        $employees = Employee::query()->with('designation')->where('property_id', $propertyId)->where('status', '!=', EmployeeStatus::Exited->value)->when($department !== null, fn ($query) => $query->where('department_id', $department))
            ->orderBy('first_name')->orderBy('last_name')->get();
        $cells = [];

        foreach (LeaveRequest::query()->with('leaveType')->whereIn('employee_id', $employees->pluck('id'))->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->get() as $leave) {
            foreach ($leave->charged_dates as $date) {
                if ($date >= $from && $date <= $to) {
                    $cells[$leave->employee_id][$date] = $leave;
                }
            }
        }

        $dates = [];

        for ($day = $month; $day->lte($month->endOfMonth()); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return view('hr::leave.calendar', [
            'month' => $month, 'dates' => $dates, 'employees' => $employees, 'cells' => $cells, 'department' => $department, 'holidays' => $schedule->holidays($propertyId, $from, $to),
            'departments' => collect($departments->all())->pluck('name', 'id')->all(), 'subtitle' => $this->propertyName(),
        ]);
    }
}
