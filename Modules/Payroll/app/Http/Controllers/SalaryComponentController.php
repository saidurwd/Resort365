<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Payroll\Actions\DeleteSalaryComponent;
use Modules\Payroll\Actions\SaveSalaryComponent;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\SalaryComponentRequest;
use Modules\Payroll\Models\SalaryComponent;

/**
 * Payroll → Salary components: the earnings, deductions and employer costs.
 */
class SalaryComponentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SalaryComponent::class);

        return view('payroll::components.index', ['records' => SalaryComponent::query()->orderBy('sort_order')->orderBy('code')->get(), 'canManage' => auth()->user()?->can('payroll.component.manage') ?? false]);
    }

    public function create(): View
    {
        Gate::authorize('create', SalaryComponent::class);

        return view('payroll::components.form', $this->formData(null));
    }

    public function store(SalaryComponentRequest $request, SaveSalaryComponent $save): RedirectResponse
    {
        Gate::authorize('create', SalaryComponent::class);

        try {
            $component = $save->handle(null, $request->validated());
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.components.index')->with('success', __('Component ":name" added.', ['name' => $component->name]));
    }

    public function edit(SalaryComponent $component): View
    {
        Gate::authorize('update', $component);

        return view('payroll::components.form', $this->formData($component));
    }

    public function update(SalaryComponentRequest $request, SalaryComponent $component, SaveSalaryComponent $save): RedirectResponse
    {
        Gate::authorize('update', $component);

        try {
            $save->handle($component, $request->validated());
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.components.index')->with('success', __('Component ":name" saved.', ['name' => $component->name]));
    }

    public function destroy(SalaryComponent $component, DeleteSalaryComponent $delete): RedirectResponse
    {
        Gate::authorize('delete', $component);

        try {
            $delete->handle($component);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.components.index')->with('success', __('Component ":name" deleted.', ['name' => $component->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?SalaryComponent $component): array
    {
        return ['record' => $component, 'kinds' => ComponentKind::options(), 'frequencies' => ComponentFrequency::options()];
    }
}
