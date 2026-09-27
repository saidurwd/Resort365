<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant routes
|--------------------------------------------------------------------------
|
| Served on {tenant}.{central domain} with the `web` and `tenant` middleware
| (see bootstrap/app.php). Module routes are registered the same way.
|
*/

// TODO(step-0.5): becomes the sign-in page / dashboard.
Route::view('/', 'tenancy.home')->name('tenant.home');
