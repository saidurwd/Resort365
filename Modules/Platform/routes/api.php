<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/platform/..., names `api.v1.platform.resource.action`.
|
*/

Route::prefix('v1/platform')->name('v1.platform.')->group(function (): void {
    //
});
