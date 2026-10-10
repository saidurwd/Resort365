<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/accounting/..., names `api.v1.accounting.resource.action`.
|
*/

Route::prefix('v1/accounting')->name('v1.accounting.')->group(function (): void {
    //
});
