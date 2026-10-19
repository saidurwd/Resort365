<?php

namespace Modules\Payroll\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\Payroll\Actions\DeleteEmployeeSalary;
use Modules\Payroll\Actions\SetEmployeeSalary;
use Modules\Payroll\DTOs\SalaryBreakdown;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\EmployeeSalaryRequest;
use Modules\Payroll\Http\Requests\PreviewRequest;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Services\SalaryPreview;
use Modules\Payroll\Services\TaxTableResolver;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Payroll → Salaries: the current property's employees with their pay, and an employee's salary history with the month's
 * breakdown (gross, deductions, tax, net).
 */
class EmployeeSalaryController extends Controller
{
    public function __construct(
        private readonly EmployeeDirectory $employees,
        private readonly PropertyContext $property,
        private readonly PropertyDirectory $properties,
    ) {}

    public function index(Request $request, SalaryPreview $preview, DepartmentDirectory $departments): View
    {
        Gate::authorize('viewAny', EmployeeSalary::class);
        $propertyId = $this->property->currentId() ?? abort(403, __('Choose a property first.'));
        $today = $this->properties->find($propertyId)->businessDate ?? now()->toDateString();
        $department = $request->filled('department') ? (int) $request->query('department') : null;
        $people = $this->employees->forProperty($propertyId, $department);
        $salaries = EmployeeSalary::query()->with('structure')->whereIn('employee_id', array_map(fn (EmployeeSummary $employee): int => $employee->id, $people))->whereDate('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))->get()->keyBy('employee_id');
        $rows = [];

        foreach ($people as $person) {
            $salary = $salaries->get($person->id);
            $rows[] = ['employee' => $person, 'salary' => $salary, 'breakdown' => $salary instanceof EmployeeSalary ? $this->safely(fn (): SalaryBreakdown => $preview->forSalary($salary, $today)) : null];
        }

        return view('payroll::salaries.index', [
            'rows' => $rows, 'department' => $department, 'departments' => collect($departments->all())->pluck('name', 'id')->all(), 'today' => $today, 'subtitle' => $this->properties->find($propertyId)->name ?? null,
            'canManage' => $request->user()?->can('payroll.salary.manage') ?? false,
        ]);
    }

    public function show(PreviewRequest $request, int $employee, SalaryPreview $preview, TaxTableResolver $taxTables, AuditTrail $audit): View
    {
        Gate::authorize('viewAny', EmployeeSalary::class);
        $person = $this->employees->find($employee) ?? abort(404);
        $date = (string) $request->validated('date', $this->properties->find($person->propertyId)->businessDate ?? now()->toDateString());
        $history = EmployeeSalary::query()->with('structure')->where('employee_id', $employee)->orderByDesc('effective_from')->get();
        $current = $preview->salaryOn($employee, $date);
        $error = null;
        $breakdown = null;

        try {
            $breakdown = $current instanceof EmployeeSalary ? $preview->forSalary($current, $date) : null;
        } catch (PayrollRuleViolated $exception) {
            $error = $exception->getMessage();
        }

        $table = $taxTables->forDate($date);
        $structures = SalaryStructure::query()->where('is_active', true)->with('lines.component')->orderBy('name')->get();

        return view('payroll::salaries.show', [
            'person' => $person, 'history' => $history, 'current' => $current, 'breakdown' => $breakdown, 'error' => $error, 'date' => $date, 'structures' => $structures->pluck('name', 'id')->all(),
            'employeeSet' => $structures->mapWithKeys(fn (SalaryStructure $structure): array => [$structure->id => $structure->lines->filter(fn ($line): bool => $line->calc_type === CalcType::Employee)->map(fn ($line): array => ['code' => $line->component->code, 'name' => $line->component->name, 'default' => (string) $line->amount])->values()->all()])->all(),
            'categories' => collect($table instanceof TaxTable ? $table->thresholds : [])->pluck('label', 'code')->all(), 'areas' => collect($table instanceof TaxTable ? $table->minimum_tax : [])->pluck('label', 'code')->all(),
            'canManage' => $request->user()?->can('payroll.salary.manage') ?? false, 'today' => now()->toDateString(), 'trail' => $current instanceof EmployeeSalary ? $audit->for($current) : [],
        ]);
    }

    public function store(EmployeeSalaryRequest $request, int $employee, SetEmployeeSalary $set): RedirectResponse
    {
        Gate::authorize('create', EmployeeSalary::class);

        try {
            $set->handle($employee, $request->validated(), $request->user()?->id);
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.salaries.show', $employee)->with('success', __('Salary saved.'));
    }

    public function destroy(int $employee, EmployeeSalary $salary, DeleteEmployeeSalary $delete): RedirectResponse
    {
        Gate::authorize('delete', $salary);
        abort_unless($salary->employee_id === $employee && $this->employees->find($employee) instanceof EmployeeSummary, 404);

        try {
            $delete->handle($salary);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.salaries.show', $employee)->with('success', __('Salary removed.'));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (PayrollRuleViolated) {
            return null;
        }
    }
}
