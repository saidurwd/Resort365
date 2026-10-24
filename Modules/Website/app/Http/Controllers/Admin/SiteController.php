<?php

namespace Modules\Website\Http\Controllers\Admin;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Website\Actions\SaveWebsiteSite;
use Modules\Website\Enums\SiteTheme;
use Modules\Website\Http\Requests\SaveWebsiteSiteRequest;
use Modules\Website\Models\WebsiteSite;

/**
 * Website → Site settings (Step 8.2a): the current property's public website.
 */
class SiteController extends Controller
{
    public function edit(PropertyContext $context, PropertyDirectory $properties): View
    {
        Gate::authorize('viewAny', WebsiteSite::class);

        $propertyId = $context->currentId() ?? abort(403, __('Choose a property first.'));
        $property = $properties->find($propertyId);
        $site = WebsiteSite::query()->where('property_id', $propertyId)->first();

        return view('website::admin.site', [
            'site' => $site,
            'property' => $property,
            'defaults' => ['name' => $property->name ?? '', 'slug' => strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $property->code ?? 'resort')),
                'phone' => $property?->phone, 'email' => $property?->email, 'address' => $property?->address],
            'themes' => SiteTheme::options(),
            'locales' => (array) config('website.locales'),
            'history' => $site instanceof WebsiteSite ? app(AuditTrail::class)->for($site) : [],
        ]);
    }

    public function update(SaveWebsiteSiteRequest $request, SaveWebsiteSite $save): RedirectResponse
    {
        $site = WebsiteSite::query()->where('property_id', $request->propertyId())->first();
        Gate::authorize($site instanceof WebsiteSite ? 'update' : 'create', $site ?? WebsiteSite::class);

        $saved = $save->handle($site, $request->propertyId(), $request->validated());

        return to_route('website.admin.site.edit')->with('success', $saved->is_published
            ? __('Website saved. It is public at :url', ['url' => route('website.home', ['site' => $saved->slug])])
            : __('Website saved. It is not published yet; use Preview to see it.'));
    }
}
