<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Property API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/property/..., names `api.v1.property.resource.action`.
|
*/

Route::prefix('v1/property')->name('v1.property.')->group(function (): void {
    //
});
