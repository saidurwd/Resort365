<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantHomePage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/` on a tenant subdomain (Step 8.2a): the company's public website when it has one (TenantHomePage), else the dashboard.
 */
class TenantHomeController
{
    public function __invoke(Request $request): Response
    {
        $home = app()->bound(TenantHomePage::class) ? app(TenantHomePage::class)->respond($request) : null;

        return $home ?? redirect('/dashboard');
    }
}
