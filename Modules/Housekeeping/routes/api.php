<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Housekeeping API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/housekeeping/..., names `api.v1.housekeeping.resource.action`.
|
*/

Route::prefix('v1/housekeeping')->name('v1.housekeeping.')->group(function (): void {
    //
});
