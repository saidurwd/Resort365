<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\DeleteLeaveType;
use Modules\HR\Actions\SaveLeaveType;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\LeaveAccrual;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\LeaveTypeRequest;
use Modules\HR\Models\LeaveType;

/**
 * Setup → Leave types: the kinds of leave and their policies.
 */
class LeaveTypeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', LeaveType::class);

        return view('hr::leave-types.index', ['records' => LeaveType::query()->with('policy')->orderBy('name')->get(), 'canManage' => auth()->user()?->can('hr.leave-type.manage') ?? false]);
    }

    public function create(): View
    {
        Gate::authorize('create', LeaveType::class);

        return view('hr::leave-types.form', $this->formData(null));
    }

    public function store(LeaveTypeRequest $request, SaveLeaveType $save): RedirectResponse
    {
        Gate::authorize('create', LeaveType::class);

        try {
            $type = $save->handle(null, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.leave-types.index')->with('success', __('Leave type ":name" added.', ['name' => $type->name]));
    }

    public function edit(LeaveType $leaveType): View
    {
        Gate::authorize('update', $leaveType);

        return view('hr::leave-types.form', $this->formData($leaveType->load('policy')));
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType, SaveLeaveType $save): RedirectResponse
    {
        Gate::authorize('update', $leaveType);

        try {
            $save->handle($leaveType, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.leave-types.index')->with('success', __('Leave type ":name" saved.', ['name' => $leaveType->name]));
    }

    public function destroy(LeaveType $leaveType, DeleteLeaveType $delete): RedirectResponse
    {
        Gate::authorize('delete', $leaveType);

        try {
            $delete->handle($leaveType);
        } catch (HrRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('hr.leave-types.index')->with('success', __('Leave type ":name" deleted.', ['name' => $leaveType->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?LeaveType $type): array
    {
        return ['record' => $type, 'policy' => $type?->policy, 'genders' => Gender::options(), 'accruals' => LeaveAccrual::options(), 'employmentTypes' => EmploymentType::options()];
    }
}
