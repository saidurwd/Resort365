<?php

namespace Modules\Property\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Contracts\ReferenceData;
use Modules\Core\Contracts\Settings;
use Modules\Property\Actions\SaveProperty;
use Modules\Property\Actions\SetupFirstRooms;
use Modules\Property\Http\Requests\PropertySetupRequest;
use Modules\Property\Http\Requests\RoomsSetupRequest;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;

/**
 * The property and rooms steps of the setup checklist (Step 7.4). Authorized by the form requests and the route permissions.
 */
class OnboardingController extends Controller
{
    public function property(ReferenceData $reference, Settings $settings): View|RedirectResponse
    {
        if (Property::query()->exists()) {
            return to_route('core.onboarding.index')->with('success', __('The property is already set up.'));
        }

        return view('property::onboarding.property', [
            'countries' => $reference->countries(), 'timezones' => $reference->timezones(), 'currencies' => $reference->currencies(),
            'timezone' => (string) $settings->get('core.timezone'), 'currency' => (string) $settings->get('core.currency'),
        ]);
    }

    public function storeProperty(PropertySetupRequest $request, SaveProperty $save): RedirectResponse
    {
        $property = $save->handle(null, [...$request->validated(), 'status' => 'active']);

        return to_route('core.onboarding.index')->with('success', __('Property ":name" created.', ['name' => $property->name]));
    }

    public function rooms(PropertyContext $context): View|RedirectResponse
    {
        if ($context->currentId() === null) {
            return to_route('property.onboarding.property')->with('error', __('Set up the property first.'));
        }

        if (Room::query()->exists()) {
            return to_route('core.onboarding.index')->with('success', __('The rooms are already set up.'));
        }

        return view('property::onboarding.rooms');
    }

    public function storeRooms(RoomsSetupRequest $request, SetupFirstRooms $setup): RedirectResponse
    {
        $made = $setup->handle($request->integer('cottage_count'), $request->integer('rooms_per_cottage'), (string) $request->input('room_type_name'), $request->integer('max_occupancy'));

        return to_route('core.onboarding.index')->with('success', trans_choice('{1} :count room created.|[2,*] :count rooms created.', $made, ['count' => $made]));
    }
}
