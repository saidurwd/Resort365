<?php

namespace Modules\IAM\Support;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Fortify's failed-login counter, keyed per tenant as well as email and IP, so the
 * same email in two tenants never shares a lockout.
 */
class TenantLoginRateLimiter extends LoginRateLimiter
{
    protected function throttleKey(Request $request)
    {
        $tenant = app(TenantContext::class)->id() ?? 'central';

        return Str::transliterate($tenant.'|'.Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());
    }
}
