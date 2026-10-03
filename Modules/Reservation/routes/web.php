<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservation\Http\Controllers\AvailabilityController;
use Modules\Reservation\Http\Controllers\BookingWizardController;
use Modules\Reservation\Http\Controllers\ReservationController;

/*
|--------------------------------------------------------------------------
| Reservation web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /reservation; route names follow `reservation.resource.action`.
| Screens work on the property chosen in the navbar.
|
*/

Route::prefix('reservation')->name('reservation.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/availability', [AvailabilityController::class, 'index'])->middleware('can:reservation.availability.view')->name('availability');

    // The booking wizard (steps 1–5).
    Route::prefix('bookings/new')->name('bookings.')->middleware('can:reservation.booking.create')->controller(BookingWizardController::class)->group(function (): void {
        Route::get('/', 'dates')->name('create');
        Route::post('/', 'storeDates')->name('create.store');
        Route::get('/choose', 'choose')->name('choose');
        Route::post('/choose', 'storeChoice')->name('choose.store');
        Route::get('/guest', 'guest')->name('guest');
        Route::post('/guest', 'storeGuest')->name('guest.store');
        Route::get('/pricing', 'pricing')->name('pricing');
        Route::post('/pricing', 'storePricing')->name('pricing.store');
        Route::get('/confirm', 'confirm')->name('confirm');
        Route::post('/confirm', 'store')->name('store');
        Route::post('/reset', 'reset')->name('reset');
    });

    Route::get('/bookings/{reservation}', [ReservationController::class, 'show'])->middleware('can:reservation.booking.view')->name('bookings.show');
});
