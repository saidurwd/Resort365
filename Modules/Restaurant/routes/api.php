<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restaurant API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/restaurant/..., names `api.v1.restaurant.resource.action`.
|
*/

Route::prefix('v1/restaurant')->name('v1.restaurant.')->group(function (): void {
    //
});
