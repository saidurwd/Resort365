<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides developer-only routes (such as /ui-kit) outside the local environment.
 */
class EnsureLocalEnvironment
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->isLocal(), 404);

        return $next($request);
    }
}
