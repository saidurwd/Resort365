<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the {tenant} subdomain and makes it current (ARCHITECTURE §4.2).
 *
 * Unknown and cancelled tenants get 404; suspended tenants get the suspended page (403).
 */
class IdentifyTenant
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        abort_unless($route instanceof Route, 404);

        $slug = $route->parameter('tenant');
        $tenant = is_string($slug) ? Tenant::query()->where('slug', strtolower($slug))->first() : null;

        abort_if($tenant === null || $tenant->status === TenantStatus::Cancelled, 404);

        if (! $tenant->status->canAccess()) {
            return response()->view('tenancy.suspended', ['tenant' => $tenant], 403);
        }

        $this->context->set($tenant);

        // Controllers never receive {tenant}; route() fills it in for the current tenant.
        $route->forgetParameter('tenant');
        URL::defaults(['tenant' => $tenant->slug]);

        return $next($request);
    }
}
