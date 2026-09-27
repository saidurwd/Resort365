<?php

namespace Modules\IAM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\IAM\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user who was deactivated (or re-invited) while signed in.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();

        if ($user instanceof User && ! $user->isActive()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('This account has been deactivated. Contact your administrator.'));
        }

        return $next($request);
    }
}
