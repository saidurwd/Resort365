<?php

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
