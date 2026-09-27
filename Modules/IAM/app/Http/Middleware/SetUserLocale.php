<?php

namespace Modules\IAM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Modules\IAM\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the signed-in user's language for the request.
 */
class SetUserLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user instanceof User && array_key_exists($user->locale, (array) config('app.available_locales'))) {
            App::setLocale($user->locale);
        }

        return $next($request);
    }
}
