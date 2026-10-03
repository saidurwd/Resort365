<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Property\Actions\DeleteDepartment;
use Modules\Property\Actions\SaveDepartment;
use Modules\Property\Http\Requests\SaveDepartmentRequest;
use Modules\Property\Models\Department;

class DepartmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Department::class);

        return view('property::departments.index', ['departments' => Department::query()->orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return view('property::departments.form', ['department' => null]);
    }

    public function store(SaveDepartmentRequest $request, SaveDepartment $save): RedirectResponse
    {
        $department = $save->handle(null, $request->validated());

        return to_route('property.departments.index')->with('success', __('Department ":name" created.', ['name' => $department->name]));
    }

    public function edit(Department $department): View
    {
        Gate::authorize('update', $department);

        return view('property::departments.form', ['department' => $department, 'history' => app(AuditTrail::class)->for($department)]);
    }

    public function update(SaveDepartmentRequest $request, Department $department, SaveDepartment $save): RedirectResponse
    {
        $save->handle($department, $request->validated());

        return to_route('property.departments.index')->with('success', __('Department ":name" saved.', ['name' => $department->name]));
    }

    public function destroy(Department $department, DeleteDepartment $delete): RedirectResponse
    {
        Gate::authorize('delete', $department);
        $delete->handle($department);

        return to_route('property.departments.index')->with('success', __('Department ":name" deleted.', ['name' => $department->name]));
    }
}
