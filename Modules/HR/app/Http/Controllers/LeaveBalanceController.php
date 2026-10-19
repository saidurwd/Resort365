<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\AdjustLeaveBalance;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\LeaveBalanceAdjustRequest;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveType;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * HR → Leave balances: each employee's available days by leave type for a year, and manual adjustments.
 */
class LeaveBalanceController extends Controller
{
    public function index(Request $request, DepartmentDirectory $departments): View
    {
        Gate::authorize('viewAny', LeaveBalance::class);
        $year = $request->integer('year', (int) now()->format('Y'));
        $department = $request->filled('department') ? (int) $request->query('department') : null;
        $types = LeaveType::query()->where('is_active', true)->where('has_balance', true)->orderBy('name')->get();
        $employees = Employee::query()->with('designation')->where('status', '!=', EmployeeStatus::Exited->value)->when($department !== null, fn ($query) => $query->where('department_id', $department))->orderBy('first_name')->orderBy('last_name')->get();
        $balances = LeaveBalance::query()->whereIn('employee_id', $employees->pluck('id'))->where('year', $year)->get()->groupBy('employee_id');

        return view('hr::leave.balances', [
            'year' => $year, 'department' => $department, 'types' => $types, 'employees' => $employees, 'balances' => $balances, 'departments' => collect($departments->all())->pluck('name', 'id')->all(),
            'canAdjust' => $request->user()?->can('adjust', LeaveBalance::class) ?? false,
        ]);
    }

    public function adjust(LeaveBalanceAdjustRequest $request, AdjustLeaveBalance $adjust): RedirectResponse
    {
        Gate::authorize('adjust', LeaveBalance::class);
        $data = $request->validated();
        $employee = Employee::query()->findOrFail((int) $data['employee_id']);

        try {
            $adjust->handle($employee, LeaveType::query()->findOrFail((int) $data['leave_type_id']), (int) $data['year'], (string) $data['days'], $data['reason'], $request->user()?->id);
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.leave.balances', ['year' => $data['year']])->with('success', __('Balance changed for :name.', ['name' => $employee->full_name]));
    }
}
