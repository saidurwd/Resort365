<?php

use Illuminate\Support\Facades\Route;
use Modules\Guest\Http\Controllers\Api\V1\GuestController;

/*
|--------------------------------------------------------------------------
| Guest API routes (Step 8.1)
|--------------------------------------------------------------------------
|
| Loaded with the `api` and `tenant-api` middleware groups under /api (names prefixed `api.`).
| Versioned: /api/v1/guest/..., names `api.v1.guest.resource.action`.
|
*/

Route::prefix('v1/guest')->name('v1.guest.')->group(function (): void {
    Route::get('/guests', [GuestController::class, 'index'])->middleware('api.can:guest.guest.view')->name('guests.index');
    Route::post('/guests', [GuestController::class, 'store'])->middleware('api.can:guest.guest.create')->name('guests.store');
    Route::get('/guests/{guest}', [GuestController::class, 'show'])->middleware('api.can:guest.guest.view')->name('guests.show');
    Route::put('/guests/{guest}', [GuestController::class, 'update'])->middleware('api.can:guest.guest.update')->name('guests.update');
});
