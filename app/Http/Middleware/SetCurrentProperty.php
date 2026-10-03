<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\PropertyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the request to the signed-in user's properties and selects the current one
 * (from the session, else the first accessible). Runs before authentication and route binding.
 */
class SetCurrentProperty
{
    public const string SESSION_KEY = 'current_property_id';

    public function __construct(private readonly PropertyContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user !== null && app()->bound(PropertyAccess::class)) {
            $accessible = app(PropertyAccess::class)->accessibleProperties($user);
            $selected = $request->session()->get(self::SESSION_KEY);

            $this->context->restrictTo($accessible, is_numeric($selected) ? (int) $selected : null);

            if ($this->context->currentId() !== null) {
                $request->session()->put(self::SESSION_KEY, $this->context->currentId());
            }
        }

        return $next($request);
    }
}
