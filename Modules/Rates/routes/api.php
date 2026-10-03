<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rates API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/rates/..., names `api.v1.rates.resource.action`.
|
*/

Route::prefix('v1/rates')->name('v1.rates.')->group(function (): void {
    //
});
