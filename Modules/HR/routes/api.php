<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/hr/..., names `api.v1.hr.resource.action`.
|
*/

Route::prefix('v1/hr')->name('v1.hr.')->group(function (): void {
    //
});
