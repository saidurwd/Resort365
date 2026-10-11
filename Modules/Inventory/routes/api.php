<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/inventory/..., names `api.v1.inventory.resource.action`.
|
*/

Route::prefix('v1/inventory')->name('v1.inventory.')->group(function (): void {
    //
});
