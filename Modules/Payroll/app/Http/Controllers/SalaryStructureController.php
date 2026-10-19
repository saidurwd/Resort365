<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Payroll\Actions\DeleteSalaryStructure;
use Modules\Payroll\Actions\SaveSalaryStructure;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\PreviewRequest;
use Modules\Payroll\Http\Requests\SalaryStructureRequest;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Services\SalaryPreview;
use Modules\Payroll\Services\TaxTableResolver;

/**
 * Payroll → Salary structures: the templates of components, with a try-out of the pay they give for a basic salary.
 */
class SalaryStructureController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', SalaryStructure::class);

        return view('payroll::structures.index', ['records' => SalaryStructure::query()->withCount('lines')->orderBy('name')->get(), 'canManage' => auth()->user()?->can('payroll.structure.manage') ?? false]);
    }

    public function show(PreviewRequest $request, SalaryStructure $structure, SalaryPreview $preview, TaxTableResolver $taxTables): View
    {
        Gate::authorize('viewAny', SalaryStructure::class);
        $structure->load('lines.component');
        $date = (string) $request->validated('date', now()->toDateString());
        $basic = (string) $request->validated('basic', '25000');
        $category = (string) $request->validated('category', 'general');
        $area = (string) $request->validated('area', 'other');
        $table = $taxTables->forDate($date);
        $error = null;
        $breakdown = null;

        try {
            $breakdown = $preview->forStructure($structure, $basic, $category, $area, $date);
        } catch (PayrollRuleViolated $exception) {
            $error = $exception->getMessage();
        }

        return view('payroll::structures.show', [
            'structure' => $structure, 'breakdown' => $breakdown, 'error' => $error, 'basic' => $basic, 'category' => $category, 'area' => $area, 'date' => $date,
            'categories' => collect($table instanceof TaxTable ? $table->thresholds : [])->pluck('label', 'code')->all(), 'areas' => collect($table instanceof TaxTable ? $table->minimum_tax : [])->pluck('label', 'code')->all(),
            'canManage' => $request->user()?->can('payroll.structure.manage') ?? false,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SalaryStructure::class);

        return view('payroll::structures.form', $this->formData(null));
    }

    public function store(SalaryStructureRequest $request, SaveSalaryStructure $save): RedirectResponse
    {
        Gate::authorize('create', SalaryStructure::class);

        try {
            $structure = $save->handle(null, $request->validated());
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.structures.show', $structure)->with('success', __('Structure ":name" added.', ['name' => $structure->name]));
    }

    public function edit(SalaryStructure $structure): View
    {
        Gate::authorize('update', $structure);

        return view('payroll::structures.form', $this->formData($structure->load('lines.component')));
    }

    public function update(SalaryStructureRequest $request, SalaryStructure $structure, SaveSalaryStructure $save): RedirectResponse
    {
        Gate::authorize('update', $structure);

        try {
            $save->handle($structure, $request->validated());
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.structures.show', $structure)->with('success', __('Structure ":name" saved.', ['name' => $structure->name]));
    }

    public function destroy(SalaryStructure $structure, DeleteSalaryStructure $delete): RedirectResponse
    {
        Gate::authorize('delete', $structure);

        try {
            $delete->handle($structure);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.structures.index')->with('success', __('Structure ":name" deleted.', ['name' => $structure->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?SalaryStructure $structure): array
    {
        $lines = $structure?->lines->map(fn ($line): array => [
            'salary_component_id' => $line->salary_component_id, 'calc_type' => $line->calc_type->value, 'amount' => $line->amount !== null ? (string) $line->amount : '', 'percent' => $line->percent !== null ? (string) $line->percent : '',
            'percent_of' => $line->percent_of ?? '', 'formula' => $line->formula ?? '',
        ])->values()->all() ?? [['salary_component_id' => '', 'calc_type' => 'fixed', 'amount' => '', 'percent' => '', 'percent_of' => '', 'formula' => '']];

        return [
            'record' => $structure, 'lines' => old('lines', $lines), 'components' => SalaryComponent::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name'])->map(fn (SalaryComponent $component): array => ['id' => $component->id, 'text' => $component->code.' · '.$component->name])->all(),
            'calcTypes' => CalcType::options(),
        ];
    }
}
