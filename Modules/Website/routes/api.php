<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Website API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/website/..., names `api.v1.website.resource.action`.
|
*/

Route::prefix('v1/website')->name('v1.website.')->group(function (): void {
    //
});
