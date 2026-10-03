<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reservation API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/reservation/..., names `api.v1.reservation.resource.action`.
|
*/

Route::prefix('v1/reservation')->name('v1.reservation.')->group(function (): void {
    //
});
