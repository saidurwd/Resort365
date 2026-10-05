<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservation\Http\Controllers\AvailabilityController;
use Modules\Reservation\Http\Controllers\BookingSourceReportController;
use Modules\Reservation\Http\Controllers\BookingWizardController;
use Modules\Reservation\Http\Controllers\QuoteController;
use Modules\Reservation\Http\Controllers\ReservationCancelController;
use Modules\Reservation\Http\Controllers\ReservationChangeController;
use Modules\Reservation\Http\Controllers\ReservationController;
use Modules\Reservation\Http\Controllers\ReservationDepositController;
use Modules\Reservation\Http\Controllers\ReservationGuestController;
use Modules\Reservation\Http\Controllers\RoomChargesController;
use Modules\Reservation\Http\Controllers\RoomingListController;
use Modules\Reservation\Http\Controllers\TapeChartController;

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
        Route::post('/quote', 'storeQuote')->middleware('can:reservation.quote.create')->name('quote');
    });

    Route::middleware('can:reservation.quote.view')->group(function (): void {
        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/quotes/data', [QuoteController::class, 'data'])->name('quotes.data');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->whereNumber('quote')->name('quotes.show');
        Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->whereNumber('quote')->name('quotes.pdf');
    });
    Route::prefix('quotes/{quote}')->whereNumber('quote')->name('quotes.')->middleware('can:reservation.quote.create')->controller(QuoteController::class)->group(function (): void {
        Route::post('/send', 'send')->name('send');
        Route::post('/convert', 'convert')->name('convert');
        Route::post('/decline', 'decline')->name('decline');
    });

    Route::get('/reports/sources', [BookingSourceReportController::class, 'index'])->middleware('can:reservation.report.view')->name('reports.sources');

    Route::get('/tape-chart', [TapeChartController::class, 'index'])->middleware('can:reservation.booking.view')->name('tape-chart');
    Route::post('/tape-chart/move', [TapeChartController::class, 'move'])->middleware('can:reservation.booking.update')->name('tape-chart.move');

    Route::middleware('can:reservation.booking.view')->group(function (): void {
        Route::get('/bookings', [ReservationController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/data', [ReservationController::class, 'data'])->name('bookings.data');
        Route::get('/bookings/{reservation}', [ReservationController::class, 'show'])->whereNumber('reservation')->name('bookings.show');
        Route::get('/bookings/{reservation}/voucher', [ReservationController::class, 'voucher'])->whereNumber('reservation')->name('bookings.voucher');
    });

    // Changes to a booking: stay (edit → review → save), deposit, guests.
    Route::prefix('bookings/{reservation}')->whereNumber('reservation')->name('bookings.')->middleware('can:reservation.booking.update')->scopeBindings()->group(function (): void {
        Route::get('/edit', [ReservationChangeController::class, 'edit'])->name('edit');
        Route::post('/edit', [ReservationChangeController::class, 'review'])->name('review');
        Route::put('/', [ReservationChangeController::class, 'update'])->name('update');
        Route::put('/deposit', [ReservationDepositController::class, 'update'])->name('deposit');
        Route::put('/room-charges', [RoomChargesController::class, 'update'])->name('room-charges');
        Route::post('/guests', [ReservationGuestController::class, 'store'])->name('guests.store');
        Route::delete('/guests/{guest}', [ReservationGuestController::class, 'destroy'])->name('guests.destroy');
        Route::put('/guests/{guest}/primary', [ReservationGuestController::class, 'primary'])->name('guests.primary');
        Route::get('/rooming-list', [RoomingListController::class, 'edit'])->name('rooming-list');
        Route::put('/rooming-list', [RoomingListController::class, 'update'])->name('rooming-list.update');
    });

    Route::prefix('bookings/{reservation}')->whereNumber('reservation')->name('bookings.')->middleware('can:reservation.booking.cancel')->group(function (): void {
        Route::get('/cancel', [ReservationCancelController::class, 'create'])->name('cancel');
        Route::post('/cancel', [ReservationCancelController::class, 'store'])->name('cancel.store');
    });
});
