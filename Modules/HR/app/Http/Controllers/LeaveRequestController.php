<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\AuditTrail;
use Modules\HR\Actions\CancelLeaveRequest;
use Modules\HR\Actions\SubmitLeaveRequest;
use Modules\HR\Enums\HalfDay;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\LeaveRequestRequest;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

/**
 * HR → Leave: every request (or one's own), a request form, a request's page with its approval, and cancelling.
 */
class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LeaveRequest::class);
        $status = LeaveStatus::tryFrom((string) $request->query('status'));

        return view('hr::leave.index', [
            'requests' => LeaveRequest::query()->with(['employee', 'leaveType'])->when($status instanceof LeaveStatus, fn ($query) => $query->where('status', $status?->value))->orderByDesc('from_date')->orderByDesc('id')->limit(200)->get(),
            'status' => $status?->value, 'statuses' => LeaveStatus::options(), 'canRequest' => $request->user()?->can('create', LeaveRequest::class) ?? false,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', LeaveRequest::class);
        $manage = $request->user()?->can('hr.leave.manage') ?? false;
        $own = Employee::query()->where('user_id', $request->user()?->id)->first();
        $chosen = $request->filled('employee') ? Employee::query()->find((int) $request->query('employee')) : null;

        return view('hr::leave.create', $this->formData($manage, $own, $chosen));
    }

    public function store(LeaveRequestRequest $request, SubmitLeaveRequest $submit): RedirectResponse
    {
        Gate::authorize('create', LeaveRequest::class);
        $data = $request->validated();
        $manage = $request->user()?->can('hr.leave.manage') ?? false;
        $employee = $manage && ! empty($data['employee_id']) ? Employee::query()->find((int) $data['employee_id']) : Employee::query()->where('user_id', $request->user()?->id)->first();

        if (! $employee instanceof Employee) {
            return back()->withInput()->with('error', $manage ? __('Choose the employee.') : __('Your user account is not linked to an employee record, so ask HR to request leave for you.'));
        }

        try {
            $leave = $submit->handle($employee, LeaveType::query()->findOrFail((int) $data['leave_type_id']), $data['from_date'], $data['to_date'], HalfDay::tryFrom((string) ($data['half_day'] ?? '')), $data['reason'] ?? null, (int) $request->user()?->id);
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.leave.show', $leave)->with('success', $leave->status === LeaveStatus::Approved ? __('Leave :no is approved.', ['no' => $leave->request_no]) : __('Leave :no is waiting for approval.', ['no' => $leave->request_no]));
    }

    public function show(Request $request, LeaveRequest $leaveRequest, ApprovalEngine $approvals, AuditTrail $audit): View
    {
        Gate::authorize('view', $leaveRequest);
        $leaveRequest->load(['employee', 'leaveType.policy']);
        $balances = LeaveBalance::query()->where(['employee_id' => $leaveRequest->employee_id, 'leave_type_id' => $leaveRequest->leave_type_id])->whereIn('year', array_map(intval(...), array_keys($leaveRequest->days_by_year)))->orderBy('year')->get();

        return view('hr::leave.show', [
            'leave' => $leaveRequest, 'approval' => $leaveRequest->approval_request_id !== null ? $approvals->find($leaveRequest->approval_request_id) : null, 'balances' => $balances,
            'canCancel' => in_array($leaveRequest->status, [LeaveStatus::Pending, LeaveStatus::Approved], true) && ($request->user()?->can('cancel', $leaveRequest) ?? false), 'trail' => $audit->for($leaveRequest),
        ]);
    }

    public function cancel(LeaveRequest $leaveRequest, CancelLeaveRequest $cancel): RedirectResponse
    {
        Gate::authorize('cancel', $leaveRequest);

        try {
            $cancel->handle($leaveRequest);
        } catch (HrRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('hr.leave.show', $leaveRequest)->with('success', __('Leave :no is cancelled.', ['no' => $leaveRequest->request_no]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(bool $manage, ?Employee $own, ?Employee $chosen): array
    {
        return [
            'manage' => $manage, 'own' => $own, 'chosen' => $chosen, 'types' => LeaveType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(), 'halves' => HalfDay::options(),
        ];
    }
}
