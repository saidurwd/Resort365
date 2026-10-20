<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/reports/..., names `api.v1.reports.resource.action`.
|
*/

Route::prefix('v1/reports')->name('v1.reports.')->group(function (): void {
    //
});
