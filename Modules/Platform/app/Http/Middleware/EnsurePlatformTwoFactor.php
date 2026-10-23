<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Platform\Models\PlatformAdmin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a platform admin without two-factor sign-in to set it up before using the console, Horizon or Pulse, when it is required:
 * `platform.require_two_factor`, which defaults to production only.
 */
class EnsurePlatformTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('platform');

        if (! $admin instanceof PlatformAdmin || ! self::required() || $admin->hasEnabledTwoFactorAuthentication() || $request->routeIs('platform.two-factor.*', 'platform.logout')) {
            return $next($request);
        }

        return redirect()->route('platform.two-factor.show')->with('warning', __('Set up two-factor sign-in to use the platform console.'));
    }

    public static function required(): bool
    {
        $setting = config('platform.require_two_factor');

        return is_bool($setting) ? $setting : app()->isProduction();
    }
}
