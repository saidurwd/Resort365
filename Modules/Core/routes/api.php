<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/core/..., names `api.v1.core.resource.action`.
|
*/

Route::prefix('v1/core')->name('v1.core.')->group(function (): void {
    //
});
