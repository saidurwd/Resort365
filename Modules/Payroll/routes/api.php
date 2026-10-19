<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payroll API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/payroll/..., names `api.v1.payroll.resource.action`.
|
*/

Route::prefix('v1/payroll')->name('v1.payroll.')->group(function (): void {
    //
});
