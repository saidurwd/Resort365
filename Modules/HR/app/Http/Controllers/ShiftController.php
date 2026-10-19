<?php

namespace Modules\HR\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\DeleteShift;
use Modules\HR\Actions\SaveShift;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Requests\ShiftRequest;
use Modules\HR\Models\Shift;

/**
 * Setup → Shifts.
 */
class ShiftController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Shift::class);

        return view('hr::shifts.index', ['records' => Shift::query()->orderBy('starts_at')->orderBy('code')->get(), 'canManage' => auth()->user()?->can('hr.shift.manage') ?? false]);
    }

    public function create(): View
    {
        Gate::authorize('create', Shift::class);

        return view('hr::shifts.form', ['record' => null]);
    }

    public function store(ShiftRequest $request, SaveShift $save): RedirectResponse
    {
        Gate::authorize('create', Shift::class);

        try {
            $shift = $save->handle(null, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.shifts.index')->with('success', __('Shift ":name" added.', ['name' => $shift->name]));
    }

    public function edit(Shift $shift): View
    {
        Gate::authorize('update', $shift);

        return view('hr::shifts.form', ['record' => $shift]);
    }

    public function update(ShiftRequest $request, Shift $shift, SaveShift $save): RedirectResponse
    {
        Gate::authorize('update', $shift);

        try {
            $save->handle($shift, $request->validated());
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('hr.shifts.index')->with('success', __('Shift ":name" saved.', ['name' => $shift->name]));
    }

    public function destroy(Shift $shift, DeleteShift $delete): RedirectResponse
    {
        Gate::authorize('delete', $shift);

        try {
            $delete->handle($shift);
        } catch (HrRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('hr.shifts.index')->with('success', __('Shift ":name" deleted.', ['name' => $shift->name]));
    }
}
