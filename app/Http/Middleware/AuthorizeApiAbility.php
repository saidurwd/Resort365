<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiAbility;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `api.can:<permission>` on API routes (Step 8.1): the token's user must hold the permission and the token must have been given it
 * (its abilities are permission names). Either missing is a 403.
 */
class AuthorizeApiAbility
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if (! $user instanceof Authorizable || ! $user->can($permission)) {
                throw new AuthorizationException(__('Your account may not do this (:permission).', ['permission' => $permission]));
            }

            if (! ApiAbility::tokenAllows($user, $permission)) {
                throw new AuthorizationException(__('This token was not given the :permission ability.', ['permission' => $permission]));
            }
        }

        return $next($request);
    }
}
