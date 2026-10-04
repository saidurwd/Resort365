<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Billing\Actions\SaveChargeCode;
use Modules\Billing\Http\Requests\SaveChargeCodeRequest;
use Modules\Billing\Models\ChargeCode;
use Modules\Core\Contracts\TaxEngine;

/**
 * Setup → Charge codes (tenant-wide).
 */
class ChargeCodeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ChargeCode::class);

        return view('billing::charge-codes.index', [
            'codes' => ChargeCode::query()->orderBy('sort_order')->orderBy('code')->get(),
            'taxCategories' => $this->taxCategories(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ChargeCode::class);

        return view('billing::charge-codes.form', ['code' => null, 'taxCategories' => $this->taxCategories()]);
    }

    public function store(SaveChargeCodeRequest $request, SaveChargeCode $save): RedirectResponse
    {
        $code = $save->handle(null, $request->validated());

        return to_route('billing.charge-codes.index')->with('success', __('Charge code :code created.', ['code' => $code->code]));
    }

    public function edit(ChargeCode $chargeCode): View
    {
        Gate::authorize('update', $chargeCode);

        return view('billing::charge-codes.form', ['code' => $chargeCode, 'taxCategories' => $this->taxCategories()]);
    }

    public function update(SaveChargeCodeRequest $request, ChargeCode $chargeCode, SaveChargeCode $save): RedirectResponse
    {
        $save->handle($chargeCode, $request->validated());

        return to_route('billing.charge-codes.index')->with('success', __('Charge code :code saved.', ['code' => $chargeCode->code]));
    }

    public function destroy(ChargeCode $chargeCode): RedirectResponse
    {
        Gate::authorize('delete', $chargeCode);
        $chargeCode->delete();

        return to_route('billing.charge-codes.index')->with('success', __('Charge code :code deleted. Lines already posted keep it.', ['code' => $chargeCode->code]));
    }

    /**
     * @return array<int, string>
     */
    private function taxCategories(): array
    {
        $options = [];

        foreach (app(TaxEngine::class)->categories() as $category) {
            $options[$category->id] = $category->name;
        }

        return $options;
    }
}
