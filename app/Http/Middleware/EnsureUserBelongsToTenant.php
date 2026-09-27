<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a session whose signed-in user belongs to a different tenant than the
 * current subdomain (ARCHITECTURE §4.2, "Auth" layer). The request continues as a guest,
 * so `auth` middleware then sends the visitor to this tenant's sign-in page.
 */
class EnsureUserBelongsToTenant
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if (! $guard instanceof SessionGuard) {
            return $next($request);
        }

        $user = $guard->user();

        if ($user instanceof Model && (int) $user->getAttribute('tenant_id') !== $this->context->id()) {
            // Not logout(): it would rotate the remember token, i.e. write to another tenant's user.
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
