<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:<alias>`: returns 403 when the module is disabled for the current tenant (ARCHITECTURE §4.3).
 * Module route providers add it to every route of the module.
 */
class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleAccess $modules) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless($this->modules->enabled($module), 403, __('This module is not enabled for your account.'));

        return $next($request);
    }
}
