<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Payroll\Actions\DeleteTaxTable;
use Modules\Payroll\Actions\SaveTaxTable;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\TaxTableRequest;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Models\TaxTable;

/**
 * Payroll → Tax tables: the income tax rules of each tax year, as data.
 */
class TaxTableController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', TaxTable::class);

        return view('payroll::tax-tables.index', ['records' => TaxTable::query()->with('slabs')->orderByDesc('effective_from')->get(), 'canManage' => auth()->user()?->can('payroll.tax.manage') ?? false]);
    }

    public function create(): View
    {
        Gate::authorize('create', TaxTable::class);

        return view('payroll::tax-tables.form', $this->formData(null));
    }

    public function store(TaxTableRequest $request, SaveTaxTable $save): RedirectResponse
    {
        Gate::authorize('create', TaxTable::class);

        try {
            $table = $save->handle(null, $request->validated());
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.tax-tables.index')->with('success', __('Tax table ":name" added.', ['name' => $table->name]));
    }

    public function edit(TaxTable $taxTable): View
    {
        Gate::authorize('update', $taxTable);

        return view('payroll::tax-tables.form', $this->formData($taxTable->load('slabs')));
    }

    public function update(TaxTableRequest $request, TaxTable $taxTable, SaveTaxTable $save): RedirectResponse
    {
        Gate::authorize('update', $taxTable);

        try {
            $save->handle($taxTable, $request->validated());
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.tax-tables.index')->with('success', __('Tax table ":name" saved.', ['name' => $taxTable->name]));
    }

    public function destroy(TaxTable $taxTable, DeleteTaxTable $delete): RedirectResponse
    {
        Gate::authorize('delete', $taxTable);
        $delete->handle($taxTable);

        return to_route('payroll.tax-tables.index')->with('success', __('Tax table ":name" deleted.', ['name' => $taxTable->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?TaxTable $table): array
    {
        $blank = ['code' => '', 'label' => '', 'amount' => ''];

        return [
            'record' => $table,
            'thresholds' => old('thresholds', $table instanceof TaxTable ? $table->thresholds : [$blank]), 'minimums' => old('minimum_tax', $table instanceof TaxTable ? $table->minimum_tax : [$blank]),
            'slabs' => old('slabs', $table instanceof TaxTable ? $table->slabs->map(fn (TaxSlab $slab): array => ['width' => $slab->width !== null ? (string) $slab->width : '', 'rate' => (string) $slab->rate])->values()->all() : [['width' => '', 'rate' => '']]),
        ];
    }
}
