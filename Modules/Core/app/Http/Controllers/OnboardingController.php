<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Actions\ApplyTaxSetup;
use Modules\Core\Contracts\Onboarding;
use Modules\Core\Http\Requests\TaxSetupRequest;
use Modules\Core\Models\TaxCategory;

/**
 * The setup checklist of a new company (Step 7.4) at /onboarding, and its tax step. Authorized by the core.onboarding.manage route
 * middleware.
 */
class OnboardingController extends Controller
{
    public function index(Onboarding $onboarding): View
    {
        return view('core::onboarding.index', ['progress' => $onboarding->progress()]);
    }

    public function dismiss(Onboarding $onboarding): RedirectResponse
    {
        $onboarding->dismiss();

        return to_route('dashboard')->with('success', __('The setup checklist is put away. You can open it again from Setup → Setup checklist.'));
    }

    public function taxes(): View|RedirectResponse
    {
        if (TaxCategory::query()->exists()) {
            return to_route('core.onboarding.index')->with('success', __('Taxes are already set up.'));
        }

        return view('core::onboarding.taxes');
    }

    public function storeTaxes(TaxSetupRequest $request, ApplyTaxSetup $setup): RedirectResponse
    {
        $setup->handle((string) $request->validated('service_charge_rate'), (string) $request->validated('vat_rate'), $request->boolean('vat_on_service_charge'));

        return to_route('core.onboarding.index')->with('success', __('Taxes are set up.'));
    }
}
