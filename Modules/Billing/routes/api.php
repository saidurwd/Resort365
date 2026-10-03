<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Billing API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/billing/..., names `api.v1.billing.resource.action`.
|
*/

Route::prefix('v1/billing')->name('v1.billing.')->group(function (): void {
    //
});
