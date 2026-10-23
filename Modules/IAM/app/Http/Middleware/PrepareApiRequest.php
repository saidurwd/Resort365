<?php

namespace Modules\IAM\Http\Middleware;

use App\Support\Api\ApiException;
use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\PropertyContext;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Contracts\PlanLimits;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `tenant-api` group's checks after a token signed the request in (Step 8.1): the tenant's plan must include the API, and the
 * request works in the properties both the user and the token may use. `property_id` (query or body) picks the property; one the
 * token may not use is a 403 `property_denied`. Also records when and from where the token was last used (at most once a minute).
 */
class PrepareApiRequest
{
    public function __construct(private readonly PlanLimits $plan, private readonly PropertyContext $properties) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $user instanceof User || ! $token instanceof ApiToken) {
            return $next($request);
        }

        if (! $this->plan->hasFeature('api_access')) {
            throw new ApiException(403, 'api_not_in_plan', __('Your plan does not include API access. Upgrade the plan to use the API.'));
        }

        $this->restrictProperties($request, $user, $token);
        $this->recordUse($request, $token);

        return $next($request);
    }

    private function restrictProperties(Request $request, User $user, ApiToken $token): void
    {
        if (! app()->bound(PropertyAccess::class)) {
            return;
        }

        $accessible = array_filter(app(PropertyAccess::class)->accessibleProperties($user), $token->allowsProperty(...), ARRAY_FILTER_USE_KEY);
        $requested = $request->input('property_id');

        if ($requested !== null && (! is_numeric($requested) || ! isset($accessible[(int) $requested]))) {
            throw new ApiException(403, 'property_denied', __('This token may not work in property :id.', ['id' => is_scalar($requested) ? (string) $requested : '?']));
        }

        $this->properties->restrictTo($accessible, $requested !== null ? (int) $requested : null);
    }

    private function recordUse(Request $request, ApiToken $token): void
    {
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute()) || $token->last_used_ip !== $request->ip()) {
            $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }
    }
}
