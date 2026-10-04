<?php

use Illuminate\Support\Facades\Route;
use Modules\FrontOffice\Http\Controllers\CheckInController;
use Modules\FrontOffice\Http\Controllers\CheckOutController;
use Modules\FrontOffice\Http\Controllers\FrontDeskController;
use Modules\FrontOffice\Http\Controllers\StayChangeController;

/*
|--------------------------------------------------------------------------
| FrontOffice web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /frontoffice; route names follow `frontoffice.resource.action`.
| Screens work on the property chosen in the navbar.
|
*/

Route::prefix('frontoffice')->name('frontoffice.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/', [FrontDeskController::class, 'index'])->middleware('can:frontoffice.desk.view')->name('desk');

    Route::prefix('check-in/{reservation}')->whereNumber('reservation')->name('check-in.')->middleware('can:frontoffice.checkin.perform')
        ->controller(CheckInController::class)->group(function (): void {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'store')->name('store');
            Route::post('/identity', 'identity')->name('identity');
            Route::post('/room', 'room')->name('room');
            Route::get('/card', 'card')->name('card');
        });

    Route::prefix('stay/{reservation}')->whereNumber('reservation')->name('stay.')->middleware('can:frontoffice.stay.change')
        ->controller(StayChangeController::class)->group(function (): void {
            Route::get('/', 'show')->name('show');
            Route::post('/move', 'move')->name('move');
            Route::post('/extend', 'extend')->name('extend');
            Route::post('/shorten', 'shorten')->name('shorten');
        });

    Route::prefix('check-out/{reservation}')->whereNumber('reservation')->name('check-out.')->middleware('can:frontoffice.checkout.perform')
        ->controller(CheckOutController::class)->group(function (): void {
            Route::get('/', 'show')->name('show');
            Route::post('/charges', 'charges')->name('charges');
            Route::post('/', 'store')->name('store');
        });
});
