<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| FrontOffice API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/frontoffice/..., names `api.v1.frontoffice.resource.action`.
|
*/

Route::prefix('v1/frontoffice')->name('v1.frontoffice.')->group(function (): void {
    //
});
