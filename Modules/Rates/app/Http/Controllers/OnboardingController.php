<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Rates\Actions\SetupFirstRates;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\RatesSetupRequest;
use Modules\Rates\Models\RatePlan;

/**
 * The prices step of the setup checklist (Step 7.4), on the current property. Authorized by the form request and the route permissions.
 */
class OnboardingController extends Controller
{
    use UsesCurrentProperty;

    public function edit(InventoryCatalog $catalog): View|RedirectResponse
    {
        $propertyId = $this->currentPropertyId();

        if (RatePlan::query()->where('property_id', $propertyId)->exists()) {
            return to_route('core.onboarding.index')->with('success', __('Prices are already set up.'));
        }

        $units = $catalog->unitTypes($propertyId, activeOnly: true);

        if ($units === []) {
            return to_route('property.onboarding.rooms')->with('error', __('Set up the cottages and rooms first.'));
        }

        return view('rates::onboarding.prices', ['units' => $units, 'currency' => $this->currency($propertyId)]);
    }

    public function store(RatesSetupRequest $request, SetupFirstRates $setup): RedirectResponse
    {
        /** @var array<string, string|null> $prices */
        $prices = (array) $request->validated('prices');
        $setup->handle($this->currentPropertyId(), $prices);

        return to_route('core.onboarding.index')->with('success', __('Prices are set up.'));
    }
}
