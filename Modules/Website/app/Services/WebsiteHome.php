<?php

namespace Modules\Website\Services;

use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantHomePage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Modules\Website\Models\WebsiteSite;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/` on the tenant's subdomain (Step 8.2a): the published website. One site goes straight to its home page; several get a list of
 * the resorts; none (or the module off) leaves `/` to the dashboard.
 */
class WebsiteHome implements TenantHomePage
{
    public function __construct(private readonly ModuleAccess $modules, private readonly PropertyContext $properties) {}

    public function respond(Request $request): ?Response
    {
        if (! $this->modules->enabled('website')) {
            return null;
        }

        /** @var Collection<int, WebsiteSite> $sites */
        $sites = $this->properties->unrestricted(fn (): Collection => WebsiteSite::query()->where('is_published', true)->orderBy('name')->get());

        return match ($sites->count()) {
            0 => null,
            1 => redirect()->route('website.home', ['site' => $sites->first()?->slug]),
            default => response()->view('website::public.resorts', ['sites' => $sites]),
        };
    }
}
