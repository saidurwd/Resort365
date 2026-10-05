<?php

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

Route::redirect('/', '/dashboard')->name('tenant.home');

// TODO(step-7.1): management dashboard with KPIs.
Route::view('/dashboard', 'tenancy.dashboard')->middleware(['auth', 'verified'])->name('dashboard');

// Private-channel authorization for Echo (Step 3.5), on the tenant's own subdomain so the tenant is known
// before a channel callback runs. Channels are defined by the modules (e.g. Restaurant's kitchen and outlet channels).
Broadcast::routes();
