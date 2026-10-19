<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\HR\Enums\HalfDay;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

/**
 * HR → My leave: the signed-in user's own balances and requests, for a user linked to an employee.
 */
class MyLeaveController extends Controller
{
    public function index(Request $request): View
    {
        $employee = Employee::query()->where('user_id', $request->user()?->id)->first();
        $year = (int) now()->format('Y');

        return view('hr::leave.mine', [
            'employee' => $employee, 'year' => $year,
            'balances' => $employee instanceof Employee ? LeaveBalance::query()->with('leaveType')->where(['employee_id' => $employee->id, 'year' => $year])->get()->sortBy(fn (LeaveBalance $balance): string => $balance->leaveType->name)->values() : collect(),
            'requests' => $employee instanceof Employee ? LeaveRequest::query()->with('leaveType')->where('employee_id', $employee->id)->orderByDesc('from_date')->limit(30)->get() : collect(),
            'types' => LeaveType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(), 'halves' => HalfDay::options(),
        ]);
    }
}
