<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservation\Http\Controllers\Api\V1\AvailabilityController;
use Modules\Reservation\Http\Controllers\Api\V1\QuoteController;
use Modules\Reservation\Http\Controllers\Api\V1\ReservationController;

/*
|--------------------------------------------------------------------------
| Reservation API routes (Step 8.1)
|--------------------------------------------------------------------------
|
| Loaded with the `api` and `tenant-api` middleware groups under /api (names prefixed `api.`).
| Versioned: /api/v1/reservation/..., names `api.v1.reservation.resource.action`.
| `api.can:<permission>`: the token's user holds the permission and the token was given it.
|
*/

Route::prefix('v1/reservation')->name('v1.reservation.')->group(function (): void {
    Route::get('/availability', AvailabilityController::class)->middleware('api.can:reservation.availability.view')->name('availability.index');

    Route::post('/quotes', [QuoteController::class, 'store'])->middleware('api.can:reservation.quote.create')->name('quotes.store');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->middleware('api.can:reservation.quote.view')->name('quotes.show');

    Route::get('/reservations', [ReservationController::class, 'index'])->middleware('api.can:reservation.booking.view')->name('reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->middleware(['api.can:reservation.booking.create', 'idempotent'])->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->middleware('api.can:reservation.booking.view')->name('reservations.show');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('api.can:reservation.booking.cancel')->name('reservations.cancel');
});
