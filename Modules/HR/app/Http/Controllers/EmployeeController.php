<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\ReferenceData;
use Modules\HR\Actions\LinkEmployeeUser;
use Modules\HR\Actions\SaveEmployee;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\HR\Enums\DisciplinaryAction;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\ExitReason;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\MaritalStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\EmployeeRequest;
use Modules\HR\Http\Requests\LinkUserRequest;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;
use Modules\HR\Services\EmployeesTable;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * HR → Employees: the directory, an employee's profile (tabs Profile, Employment, Documents, History) and the form.
 */
class EmployeeController extends Controller
{
    public function __construct(
        private readonly DepartmentDirectory $departments,
        private readonly PropertyDirectory $properties,
        private readonly ReferenceData $reference,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Employee::class);
        $filter = $this->filter($request);

        return view('hr::employees.index', [
            'columns' => EmployeesTable::columns(), 'filter' => $filter, 'search' => (string) $request->query('search', ''),
            'departments' => collect($this->departments->all())->pluck('name', 'id')->all(), 'department' => $request->filled('department') ? (int) $request->query('department') : null,
        ]);
    }

    public function data(Request $request, EmployeesTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);
        $search = $request->input('search.value');

        return $table->toJson($this->filter($request), is_string($search) ? trim($search) : '', $request->filled('department') ? (int) $request->query('department') : null);
    }

    /**
     * Employees for pickers (Tom Select remote search): [{value, text}].
     */
    public function search(Request $request, EmployeeDirectory $directory): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        return response()->json(array_map(fn (EmployeeSummary $employee): array => ['value' => $employee->id, 'text' => $employee->employeeNo.' · '.$employee->name.' · '.$employee->designation],
            $directory->search((string) $request->query('q', ''), 15)));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Employee::class);

        return view('hr::employees.form', $this->formData(null, $request->user()?->can('hr.employee.view-sensitive') ?? false));
    }

    public function store(EmployeeRequest $request, SaveEmployee $save): RedirectResponse
    {
        Gate::authorize('create', Employee::class);

        try {
            $employee = $save->handle(null, $request->employeeData(), (int) $request->user()?->id);
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.employees.show', $employee)->with('success', __('Employee :no added.', ['no' => $employee->employee_no]));
    }

    public function show(Request $request, Employee $employee, UserDirectory $users, AuditTrail $audit): View
    {
        Gate::authorize('view', $employee);
        $employee->load(['designation', 'grade', 'manager', 'histories', 'reports.designation']);
        $user = collect($users->all())->firstWhere('id', $employee->user_id);

        return view('hr::employees.show', [
            'employee' => $employee, 'departments' => collect($this->departments->all(false))->pluck('name', 'id')->all(), 'properties' => collect($this->properties->all())->pluck('name', 'id')->all(),
            'countries' => $this->reference->countries(), 'user' => $user, 'userOptions' => $this->userOptions($users), 'sensitive' => $request->user()?->can('viewSensitive', $employee) ?? false,
            'canManage' => $request->user()?->can('update', $employee) ?? false, 'canLifecycle' => $request->user()?->can('manageLifecycle', $employee) ?? false,
            'designations' => Designation::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(), 'grades' => Grade::query()->where('is_active', true)->orderBy('level')->pluck('name', 'id')->all(),
            'disciplinary' => DisciplinaryAction::options(), 'exitReasons' => ExitReason::options(), 'trail' => $audit->for($employee), 'isProbation' => $employee->status === EmployeeStatus::OnProbation,
        ]);
    }

    public function edit(Request $request, Employee $employee): View
    {
        Gate::authorize('update', $employee);

        return view('hr::employees.form', $this->formData($employee, $request->user()?->can('viewSensitive', $employee) ?? false));
    }

    public function update(EmployeeRequest $request, Employee $employee, SaveEmployee $save): RedirectResponse
    {
        Gate::authorize('update', $employee);

        try {
            $save->handle($employee, $request->employeeData(), (int) $request->user()?->id);
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.employees.show', $employee)->with('success', __('Employee saved.'));
    }

    public function linkUser(LinkUserRequest $request, Employee $employee, LinkEmployeeUser $link): RedirectResponse
    {
        Gate::authorize('update', $employee);

        try {
            $link->handle($employee, $request->filled('user_id') ? (int) $request->validated('user_id') : null);
        } catch (HrRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('hr.employees.show', $employee)->with('success', $request->filled('user_id') ? __('Account linked.') : __('Account unlinked.'));
    }

    private function filter(Request $request): string
    {
        return in_array($request->query('filter'), EmployeesTable::FILTERS, true) ? (string) $request->query('filter') : 'all';
    }

    /**
     * @return array<int, string>
     */
    private function userOptions(UserDirectory $users): array
    {
        return collect($users->all())->mapWithKeys(fn ($user): array => [$user->id => $user->name.' · '.$user->email])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Employee $employee, bool $sensitive): array
    {
        return [
            'employee' => $employee?->loadMissing('manager'), 'sensitive' => $sensitive, 'countries' => $this->reference->countries(), 'departments' => collect($this->departments->all())->pluck('name', 'id')->all(),
            'designations' => Designation::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(), 'grades' => Grade::query()->where('is_active', true)->orderBy('level')->pluck('name', 'id')->all(),
            'types' => EmploymentType::options(), 'genders' => Gender::options(), 'maritalStatuses' => MaritalStatus::options(),
            'manager' => $employee?->manager_id !== null ? $employee->manager : null,
        ];
    }
}
