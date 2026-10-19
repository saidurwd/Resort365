<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\HR\Actions\ConfirmEmployee;
use Modules\HR\Actions\ExitEmployee;
use Modules\HR\Actions\PromoteEmployee;
use Modules\HR\Actions\RecordDisciplinary;
use Modules\HR\Actions\RecordIncrement;
use Modules\HR\Actions\TransferEmployee;
use Modules\HR\Enums\DisciplinaryAction;
use Modules\HR\Enums\ExitReason;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\LifecycleRequest;
use Modules\HR\Models\Employee;

/**
 * HR → an employee's lifecycle events: confirmation, transfer, promotion, increment, disciplinary record, exit.
 */
class EmployeeLifecycleController extends Controller
{
    public function store(LifecycleRequest $request, Employee $employee, string $event): RedirectResponse
    {
        Gate::authorize('manageLifecycle', $employee);
        $data = $request->validated();
        $notes = isset($data['notes']) ? (string) $data['notes'] : null;
        $on = (string) $data['effective_on'];
        $by = (int) $request->user()?->id;
        $department = isset($data['department_id']) && $data['department_id'] !== '' ? (int) $data['department_id'] : null;

        try {
            match ($event) {
                'confirm' => app(ConfirmEmployee::class)->handle($employee, $on, $notes, $by),
                'transfer' => app(TransferEmployee::class)->handle($employee, (int) $data['property_id'], $department, $on, $notes, $by),
                'promote' => app(PromoteEmployee::class)->handle($employee, (int) $data['designation_id'], isset($data['grade_id']) && $data['grade_id'] !== '' ? (int) $data['grade_id'] : null, $on, $notes, $by),
                'increment' => app(RecordIncrement::class)->handle($employee, (string) $data['previous_amount'], (string) $data['new_amount'], $on, $notes, $by),
                'disciplinary' => app(RecordDisciplinary::class)->handle($employee, DisciplinaryAction::from((string) $data['action']), $on, (string) $notes, $by),
                default => app(ExitEmployee::class)->handle($employee, ExitReason::from((string) $data['reason']), $on, $notes, $by),
            };
        } catch (HrRuleViolated $exception) {
            return to_route('hr.employees.show', $employee)->with('error', $exception->getMessage())->withInput();
        }

        return to_route('hr.employees.show', $employee)->with('success', __('Recorded.'))->withFragment('history');
    }
}
