<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Api\V1\FolioController;

/*
|--------------------------------------------------------------------------
| Billing API routes (Step 8.1)
|--------------------------------------------------------------------------
|
| Loaded with the `api` and `tenant-api` middleware groups under /api (names prefixed `api.`).
| Versioned: /api/v1/billing/..., names `api.v1.billing.resource.action`.
|
*/

Route::prefix('v1/billing')->name('v1.billing.')->group(function (): void {
    Route::get('/reservations/{reservation}/folios', [FolioController::class, 'forReservation'])->whereNumber('reservation')->middleware('api.can:billing.folio.view')->name('reservation-folios.index');
    Route::get('/folios/{folio}', [FolioController::class, 'show'])->middleware('api.can:billing.folio.view')->name('folios.show');
});
