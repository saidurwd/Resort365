<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\DeleteDesignation;
use Modules\HR\Actions\SaveDesignation;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\DesignationRequest;
use Modules\HR\Models\Designation;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Setup → Designations.
 */
class DesignationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Designation::class);

        return view('hr::designations.index', ['records' => Designation::query()->withCount('employees')->orderBy('name')->get(), 'departments' => collect(app(DepartmentDirectory::class)->all(false))->pluck('name', 'id')->all(), 'canManage' => auth()->user()?->can('hr.setup.manage') ?? false]);
    }

    public function create(): View
    {
        Gate::authorize('create', Designation::class);

        return view('hr::designations.form', ['record' => null, 'departments' => collect(app(DepartmentDirectory::class)->all())->pluck('name', 'id')->all()]);
    }

    public function store(DesignationRequest $request, SaveDesignation $save): RedirectResponse
    {
        Gate::authorize('create', Designation::class);

        try {
            $record = $save->handle(null, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.designations.index')->with('success', __('":name" added.', ['name' => $record->name]));
    }

    public function edit(Designation $designation): View
    {
        Gate::authorize('update', $designation);

        return view('hr::designations.form', ['record' => $designation, 'departments' => collect(app(DepartmentDirectory::class)->all())->pluck('name', 'id')->all()]);
    }

    public function update(DesignationRequest $request, Designation $designation, SaveDesignation $save): RedirectResponse
    {
        Gate::authorize('update', $designation);

        try {
            $save->handle($designation, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.designations.index')->with('success', __('":name" saved.', ['name' => $designation->name]));
    }

    public function destroy(Designation $designation, DeleteDesignation $delete): RedirectResponse
    {
        Gate::authorize('delete', $designation);

        try {
            $delete->handle($designation);
        } catch (HrRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('hr.designations.index')->with('success', __('":name" deleted.', ['name' => $designation->name]));
    }
}
