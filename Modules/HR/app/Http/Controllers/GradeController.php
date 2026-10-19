<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\DeleteGrade;
use Modules\HR\Actions\SaveGrade;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\GradeRequest;
use Modules\HR\Models\Grade;

/**
 * Setup → Grades.
 */
class GradeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Grade::class);

        return view('hr::grades.index', ['records' => Grade::query()->withCount('employees')->orderBy('level')->orderBy('code')->get(), 'canManage' => auth()->user()?->can('hr.setup.manage') ?? false]);
    }

    public function create(): View
    {
        Gate::authorize('create', Grade::class);

        return view('hr::grades.form', ['record' => null]);
    }

    public function store(GradeRequest $request, SaveGrade $save): RedirectResponse
    {
        Gate::authorize('create', Grade::class);

        try {
            $record = $save->handle(null, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.grades.index')->with('success', __('":name" added.', ['name' => $record->name]));
    }

    public function edit(Grade $grade): View
    {
        Gate::authorize('update', $grade);

        return view('hr::grades.form', ['record' => $grade]);
    }

    public function update(GradeRequest $request, Grade $grade, SaveGrade $save): RedirectResponse
    {
        Gate::authorize('update', $grade);

        try {
            $save->handle($grade, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.grades.index')->with('success', __('":name" saved.', ['name' => $grade->name]));
    }

    public function destroy(Grade $grade, DeleteGrade $delete): RedirectResponse
    {
        Gate::authorize('delete', $grade);

        try {
            $delete->handle($grade);
        } catch (HrRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('hr.grades.index')->with('success', __('":name" deleted.', ['name' => $grade->name]));
    }
}
