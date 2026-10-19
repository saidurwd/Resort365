<?php

namespace Modules\HR\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Models\Employee;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of employees (Step 6.1) across the properties the user may see. The search box matches the
 * employee number, phone and the start of the first or last name (indexed prefixes, not '%…%').
 */
class EmployeesTable
{
    public const array FILTERS = ['all', 'active', 'probation', 'exited'];

    public function __construct(
        private readonly DataTables $dataTables,
        private readonly DepartmentDirectory $departments,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'employee_no', 'title' => __('No.')],
            ['data' => 'first_name', 'title' => __('Employee')],
            ['data' => 'department', 'title' => __('Department'), 'orderable' => false, 'searchable' => false],
            ['data' => 'designation', 'title' => __('Designation'), 'orderable' => false, 'searchable' => false],
            ['data' => 'property', 'title' => __('Property'), 'orderable' => false, 'searchable' => false],
            ['data' => 'employment_type', 'title' => __('Type'), 'searchable' => false],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(string $filter, string $term, ?int $departmentId): JsonResponse
    {
        $departments = collect($this->departments->all(false))->pluck('name', 'id');
        $properties = collect($this->properties->all())->pluck('name', 'id');
        $query = Employee::query()->select('employees.*')->with('designation')
            ->when($filter === 'active', fn (Builder $query) => $query->where('status', EmployeeStatus::Active->value))
            ->when($filter === 'probation', fn (Builder $query) => $query->where('status', EmployeeStatus::OnProbation->value))
            ->when($filter === 'exited', fn (Builder $query) => $query->where('status', EmployeeStatus::Exited->value))
            ->when($filter === 'all', fn (Builder $query) => $query->where('status', '!=', EmployeeStatus::Exited->value))
            ->when($departmentId !== null, fn (Builder $query) => $query->where('department_id', $departmentId));

        return $this->dataTables->eloquent($query)
            ->filter(function (Builder $query) use ($term): void {
                if ($term === '') {
                    return;
                }

                $query->where(fn (Builder $inner) => $inner->where('employee_no', 'like', $term.'%')->orWhere('phone', 'like', $term.'%')->orWhere('first_name', 'like', $term.'%')
                    ->orWhere('last_name', 'like', $term.'%')->orWhereRaw("CONCAT(first_name, ' ', COALESCE(last_name, '')) like ?", [$term.'%']));
            }, false)
            ->editColumn('first_name', fn (Employee $employee): string => '<a href="'.e(route('hr.employees.show', $employee)).'" class="fw-semibold">'.e($employee->full_name).'</a>')
            ->addColumn('department', fn (Employee $employee): string => $departments[$employee->department_id] ?? '—')
            ->addColumn('designation', fn (Employee $employee): string => $employee->designation->name)
            ->addColumn('property', fn (Employee $employee): string => $properties[$employee->property_id] ?? '—')
            ->editColumn('employment_type', fn (Employee $employee): string => $employee->employment_type->label())
            ->editColumn('status', fn (Employee $employee): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $employee->status]))
            ->addColumn('actions', fn (Employee $employee): string => '<a href="'.e(route('hr.employees.show', $employee)).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> '.e(__('Open')).'</a>')
            ->rawColumns(['first_name', 'status', 'actions'])
            ->toJson();
    }
}
