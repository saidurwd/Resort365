<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservation\Http\Controllers\AvailabilityController;
use Modules\Reservation\Http\Controllers\BookingWizardController;
use Modules\Reservation\Http\Controllers\ReservationCancelController;
use Modules\Reservation\Http\Controllers\ReservationChangeController;
use Modules\Reservation\Http\Controllers\ReservationController;
use Modules\Reservation\Http\Controllers\ReservationDepositController;
use Modules\Reservation\Http\Controllers\ReservationGuestController;

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

    Route::middleware('can:reservation.booking.view')->group(function (): void {
        Route::get('/bookings', [ReservationController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/data', [ReservationController::class, 'data'])->name('bookings.data');
        Route::get('/bookings/{reservation}', [ReservationController::class, 'show'])->whereNumber('reservation')->name('bookings.show');
    });

    // Changes to a booking: stay (edit → review → save), deposit, guests.
    Route::prefix('bookings/{reservation}')->whereNumber('reservation')->name('bookings.')->middleware('can:reservation.booking.update')->scopeBindings()->group(function (): void {
        Route::get('/edit', [ReservationChangeController::class, 'edit'])->name('edit');
        Route::post('/edit', [ReservationChangeController::class, 'review'])->name('review');
        Route::put('/', [ReservationChangeController::class, 'update'])->name('update');
        Route::put('/deposit', [ReservationDepositController::class, 'update'])->name('deposit');
        Route::post('/guests', [ReservationGuestController::class, 'store'])->name('guests.store');
        Route::delete('/guests/{guest}', [ReservationGuestController::class, 'destroy'])->name('guests.destroy');
        Route::put('/guests/{guest}/primary', [ReservationGuestController::class, 'primary'])->name('guests.primary');
    });

    Route::prefix('bookings/{reservation}')->whereNumber('reservation')->name('bookings.')->middleware('can:reservation.booking.cancel')->group(function (): void {
        Route::get('/cancel', [ReservationCancelController::class, 'create'])->name('cancel');
        Route::post('/cancel', [ReservationCancelController::class, 'store'])->name('cancel.store');
    });
});
