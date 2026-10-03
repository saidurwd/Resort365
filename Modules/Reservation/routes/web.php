<?php

use Illuminate\Support\Facades\Route;
use Modules\Reservation\Http\Controllers\AvailabilityController;

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
});
