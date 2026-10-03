<?php

namespace Modules\Core\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Actions\DeleteTax;
use Modules\Core\Actions\SaveTax;
use Modules\Core\Actions\SaveTaxCategory;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\Settings;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\Exceptions\TaxInUse;
use Modules\Core\Http\Requests\SaveTaxCategoryRequest;
use Modules\Core\Http\Requests\SaveTaxRequest;
use Modules\Core\Http\Requests\TryTaxesRequest;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;

/**
 * Setup → Taxes: taxes, tax categories and a "try it" calculator. Authorized by the
 * core.tax.view / core.tax.manage route middleware and the form requests.
 */
class TaxController extends Controller
{
    public function index(TryTaxesRequest $request, TaxEngine $engine, Settings $settings): View
    {
        $tryCategory = $request->filled('category') ? $request->integer('category') : null;
        $breakdown = null;
        $error = null;

        if ($request->filled('amount')) {
            try {
                $breakdown = $engine->calculate((string) $request->input('amount'), $tryCategory, $request->boolean('inclusive'), max(1, $request->integer('quantity', 1)));
            } catch (DomainException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('core::taxes.index', [
            'taxes' => Tax::query()->withCount('categories')->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => TaxCategory::query()->with('taxes')->orderBy('name')->get(),
            'breakdown' => $breakdown,
            'tryError' => $error,
            'currency' => (string) $settings->get('core.currency'),
        ]);
    }

    public function create(): View
    {
        return view('core::taxes.tax-form', ['tax' => null, 'history' => []]);
    }

    public function store(SaveTaxRequest $request, SaveTax $save): RedirectResponse
    {
        $tax = $save->handle(null, $request->validated());

        return to_route('core.taxes.index')->with('success', __('Tax ":name" created.', ['name' => $tax->name]));
    }

    public function edit(Tax $tax): View
    {
        return view('core::taxes.tax-form', ['tax' => $tax, 'history' => app(AuditTrail::class)->for($tax)]);
    }

    public function update(SaveTaxRequest $request, Tax $tax, SaveTax $save): RedirectResponse
    {
        $save->handle($tax, $request->validated());

        return to_route('core.taxes.index')->with('success', __('Tax ":name" saved.', ['name' => $tax->name]));
    }

    public function destroy(Tax $tax, DeleteTax $delete): RedirectResponse
    {
        try {
            $delete->handle($tax);
        } catch (TaxInUse $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('core.taxes.index')->with('success', __('Tax ":name" deleted.', ['name' => $tax->name]));
    }

    public function createCategory(): View
    {
        return view('core::taxes.category-form', $this->categoryForm(null));
    }

    public function storeCategory(SaveTaxCategoryRequest $request, SaveTaxCategory $save): RedirectResponse
    {
        $category = $save->handle(null, $request->validated());

        return to_route('core.taxes.index')->with('success', __('Tax category ":name" created.', ['name' => $category->name]));
    }

    public function editCategory(TaxCategory $taxCategory): View
    {
        return view('core::taxes.category-form', $this->categoryForm($taxCategory));
    }

    public function updateCategory(SaveTaxCategoryRequest $request, TaxCategory $taxCategory, SaveTaxCategory $save): RedirectResponse
    {
        $save->handle($taxCategory, $request->validated());

        return to_route('core.taxes.index')->with('success', __('Tax category ":name" saved.', ['name' => $taxCategory->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryForm(?TaxCategory $category): array
    {
        return [
            'category' => $category,
            'taxes' => Tax::query()->orderBy('sort_order')->orderBy('name')->get(),
            'selected' => $category?->taxes()->pluck('taxes.id')->all() ?? [],
            'history' => $category instanceof TaxCategory ? app(AuditTrail::class)->for($category) : [],
        ];
    }
}
