<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/guest/..., names `api.v1.guest.resource.action`.
|
*/

Route::prefix('v1/guest')->name('v1.guest.')->group(function (): void {
    //
});
