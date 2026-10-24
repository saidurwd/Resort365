<?php

use App\Http\Controllers\TenantHomeController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant routes
|--------------------------------------------------------------------------
|
| Served on {tenant}.{central domain} with the `web` and `tenant` middleware
| (see bootstrap/app.php). Module routes are registered the same way.
| Sign-in and account routes come from Laravel Fortify via the IAM module.
|
*/

// The company's public website when it has one (Step 8.2a), else the dashboard.
Route::get('/', TenantHomeController::class)->name('tenant.home');

// The management dashboard (Step 7.1) is the Reports module's: /dashboard.

// Private-channel authorization for Echo (Step 3.5), on the tenant's own subdomain so the tenant is known
// before a channel callback runs. Channels are defined by the modules (e.g. Restaurant's kitchen and outlet channels).
Broadcast::routes();
