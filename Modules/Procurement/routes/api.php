<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/procurement/..., names `api.v1.procurement.resource.action`.
|
*/

Route::prefix('v1/procurement')->name('v1.procurement.')->group(function (): void {
    //
});
