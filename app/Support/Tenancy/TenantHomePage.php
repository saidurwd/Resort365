<?php

namespace App\Support\Tenancy;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What `/` on a tenant subdomain shows (Step 8.2a). The Website module answers with the company's published site; with no answer
 * (no published site, the module off or not installed) `/` goes to the dashboard as before.
 */
interface TenantHomePage
{
    public function respond(Request $request): ?Response;
}
