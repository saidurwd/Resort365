<?php

use Illuminate\Support\Facades\Route;
use Modules\Guest\Http\Controllers\CompanyController;
use Modules\Guest\Http\Controllers\GuestController;
use Modules\Guest\Http\Controllers\GuestStatusController;
use Modules\Guest\Http\Controllers\TravelAgentController;

/*
|--------------------------------------------------------------------------
| Guest web routes
|--------------------------------------------------------------------------
|
| URLs are prefixed with /guest and route names follow `guest.resource.action`.
| "Write" routes are registered before {record} routes, so /create is not taken for a record.
|
*/

Route::prefix('guest')->name('guest.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('can:guest.guest.create')->group(function (): void {
        Route::get('/guests/create', [GuestController::class, 'create'])->name('guests.create');
        Route::post('/guests', [GuestController::class, 'store'])->name('guests.store');
    });
    Route::middleware('can:guest.guest.update')->group(function (): void {
        Route::get('/guests/{guest}/edit', [GuestController::class, 'edit'])->name('guests.edit');
        Route::put('/guests/{guest}', [GuestController::class, 'update'])->name('guests.update');
    });
    Route::middleware('can:guest.guest.blacklist')->group(function (): void {
        Route::post('/guests/{guest}/blacklist', [GuestStatusController::class, 'blacklist'])->name('guests.blacklist');
        Route::delete('/guests/{guest}/blacklist', [GuestStatusController::class, 'clearBlacklist'])->name('guests.blacklist.clear');
    });
    Route::post('/guests/{guest}/merge', [GuestStatusController::class, 'merge'])->middleware('can:guest.guest.merge')->name('guests.merge');

    Route::middleware('can:guest.guest.view')->group(function (): void {
        Route::get('/guests', [GuestController::class, 'index'])->name('guests.index');
        Route::get('/guests/data', [GuestController::class, 'data'])->name('guests.data');
        Route::get('/guests/search', [GuestController::class, 'search'])->middleware('throttle:120,1')->name('guests.search');
        Route::get('/guests/{guest}', [GuestController::class, 'show'])->name('guests.show');
    });

    $write = ['create', 'store', 'edit', 'update', 'destroy'];

    Route::resource('companies', CompanyController::class)->only($write)->middleware('can:guest.company.manage');
    Route::get('/companies', [CompanyController::class, 'index'])->middleware('can:guest.company.view')->name('companies.index');

    Route::resource('travel-agents', TravelAgentController::class)->only($write)->middleware('can:guest.travel-agent.manage');
    Route::get('/travel-agents', [TravelAgentController::class, 'index'])->middleware('can:guest.travel-agent.view')->name('travel-agents.index');
});
