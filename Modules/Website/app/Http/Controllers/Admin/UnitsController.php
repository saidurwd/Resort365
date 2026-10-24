<?php

namespace Modules\Website\Http\Controllers\Admin;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Contracts\UnitShowcases;
use Modules\Website\Actions\SaveWebsiteUnits;
use Modules\Website\Http\Requests\SaveWebsiteUnitsRequest;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Models\WebsiteUnit;

/**
 * Website → Rooms and cottages (Step 8.2a): how each room type and cottage type of the current property appears on its site.
 */
class UnitsController extends Controller
{
    public function edit(PropertyContext $context, UnitShowcases $showcases): View|RedirectResponse
    {
        Gate::authorize('viewAny', WebsiteSite::class);

        $site = WebsiteSite::query()->where('property_id', $context->currentId() ?? abort(403, __('Choose a property first.')))->first();

        if (! $site instanceof WebsiteSite) {
            return to_route('website.admin.site.edit')->with('error', __('Set up the website first.'));
        }

        return view('website::admin.units', [
            'site' => $site,
            'units' => $showcases->forProperty($site->property_id),
            'settings' => WebsiteUnit::query()->where('website_site_id', $site->id)->get()->keyBy('unit_key'),
            'locales' => (array) config('website.locales'),
        ]);
    }

    public function update(SaveWebsiteUnitsRequest $request, WebsiteSite $site, SaveWebsiteUnits $save): RedirectResponse
    {
        /** @var array<string, array{headline?: array<string, string|null>, description?: array<string, string|null>, is_featured?: bool, is_hidden?: bool, sort_order?: int}> $rows */
        $rows = (array) $request->validated('units');
        $save->handle($site, $rows);

        return to_route('website.admin.units.edit')->with('success', __('Rooms and cottages on the website saved.'));
    }
}
