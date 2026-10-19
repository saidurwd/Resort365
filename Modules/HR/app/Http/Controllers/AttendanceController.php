<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\DeleteAttendance;
use Modules\HR\Actions\RecordAttendance;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Controllers\Concerns\HrScreen;
use Modules\HR\Http\Requests\AttendanceReportRequest;
use Modules\HR\Http\Requests\AttendanceRequest;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;
use Modules\HR\Services\ScheduleLookup;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * HR → Attendance: one day of the current property's employees with the rostered shift and what was recorded; enter or
 * correct a day by hand.
 */
class AttendanceController extends Controller
{
    use HrScreen;

    public function index(AttendanceReportRequest $request, ScheduleLookup $schedule, DepartmentDirectory $departments): View
    {
        Gate::authorize('viewAny', Attendance::class);
        $date = (string) $request->validated('date', $this->today());
        $department = $request->filled('department') ? (int) $request->validated('department') : null;
        $propertyId = $this->propertyId();
        $employees = Employee::query()->with('designation')->where('property_id', $propertyId)->where('status', '!=', EmployeeStatus::Exited->value)->whereDate('joined_on', '<=', $date)
            ->when($department !== null, fn ($query) => $query->where('department_id', $department))->orderBy('first_name')->orderBy('last_name')->get();
        $ids = $employees->pluck('id')->all();

        return view('hr::attendance.index', [
            'date' => $date, 'department' => $department, 'employees' => $employees, 'records' => Attendance::query()->with('shift')->whereIn('employee_id', $ids)->whereDate('work_date', $date)->get()->keyBy('employee_id'),
            'rosters' => $schedule->rosters($ids, $date, $date), 'leaves' => $schedule->leave($ids, $date, $date), 'holiday' => $schedule->holidays($propertyId, $date, $date)[$date] ?? null, 'departments' => collect($departments->all())->pluck('name', 'id')->all(),
            'canRecord' => $request->user()?->can('hr.attendance.record') ?? false, 'overrides' => [AttendanceStatus::Absent->value => AttendanceStatus::Absent->label(), AttendanceStatus::HalfDay->value => AttendanceStatus::HalfDay->label()],
            'subtitle' => $this->propertyName(),
        ]);
    }

    public function store(AttendanceRequest $request, RecordAttendance $record): RedirectResponse
    {
        Gate::authorize('create', Attendance::class);
        $data = $request->validated();
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);

        try {
            $record->handle($employee, $data['work_date'], $data['clock_in'] ?? null, $data['clock_out'] ?? null, AttendanceStatus::tryFrom((string) ($data['status'] ?? '')), $data['notes'] ?? null, $request->user()?->id);
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.attendance.index', ['date' => $data['work_date']])->with('success', __('Attendance saved for :name.', ['name' => $employee->full_name]));
    }

    public function destroy(Attendance $attendance, DeleteAttendance $delete): RedirectResponse
    {
        Gate::authorize('delete', $attendance);
        $delete->handle($attendance);

        return to_route('hr.attendance.index', ['date' => $attendance->work_date->toDateString()])->with('success', __('Attendance record removed.'));
    }
}
