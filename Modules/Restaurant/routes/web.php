<?php

use Illuminate\Support\Facades\Route;
use Modules\Restaurant\Http\Controllers\OutletAccessController;
use Modules\Restaurant\Http\Controllers\OutletController;
use Modules\Restaurant\Http\Controllers\OutletSetupController;
use Modules\Restaurant\Http\Controllers\PrinterController;

/*
|--------------------------------------------------------------------------
| Restaurant web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /restaurant; route names follow `restaurant.resource.action`.
| Setup screens work on the property chosen in the navbar. The POS (/pos) comes in Step 3.3.
|
*/

Route::prefix('restaurant')->name('restaurant.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('can:restaurant.outlet.view')->group(function (): void {
        Route::get('/outlets', [OutletController::class, 'index'])->name('outlets.index');
        Route::get('/outlets/{outlet}', [OutletController::class, 'show'])->whereNumber('outlet')->name('outlets.show');
        Route::get('/printers', [PrinterController::class, 'index'])->name('printers.index');
    });

    Route::middleware('can:restaurant.outlet.manage')->group(function (): void {
        Route::get('/outlets/new', [OutletController::class, 'create'])->name('outlets.create');
        Route::post('/outlets', [OutletController::class, 'store'])->name('outlets.store');
        Route::get('/outlets/{outlet}/edit', [OutletController::class, 'edit'])->name('outlets.edit');
        Route::put('/outlets/{outlet}', [OutletController::class, 'update'])->name('outlets.update');
        Route::post('/printers', [PrinterController::class, 'store'])->name('printers.store');
        Route::put('/printers/{printer}', [PrinterController::class, 'update'])->name('printers.update');
        Route::delete('/printers/{printer}', [PrinterController::class, 'destroy'])->name('printers.destroy');

        Route::prefix('outlets/{outlet}')->name('outlets.')->scopeBindings()->controller(OutletSetupController::class)->group(function (): void {
            Route::post('/stations', 'storeStation')->name('stations.store');
            Route::put('/stations/{station}', 'updateStation')->name('stations.update');
            Route::delete('/stations/{station}', 'destroyStation')->name('stations.destroy');
            Route::post('/terminals', 'storeTerminal')->name('terminals.store');
            Route::put('/terminals/{terminal}', 'updateTerminal')->name('terminals.update');
            Route::post('/terminals/{terminal}/token', 'newToken')->name('terminals.token');
        });
    });

    Route::prefix('outlets/{outlet}')->name('outlets.')->middleware('can:restaurant.floor-plan.manage')->scopeBindings()->controller(OutletSetupController::class)->group(function (): void {
        Route::post('/areas', 'storeArea')->name('areas.store');
        Route::put('/areas/{area}', 'updateArea')->name('areas.update');
        Route::delete('/areas/{area}', 'destroyArea')->name('areas.destroy');
        Route::post('/areas/{area}/positions', 'positions')->name('areas.positions');
        Route::post('/tables', 'storeTable')->name('tables.store');
        Route::put('/tables/{table}', 'updateTable')->name('tables.update');
        Route::delete('/tables/{table}', 'destroyTable')->name('tables.destroy');
    });

    Route::get('/access', [OutletAccessController::class, 'index'])->middleware('can:restaurant.access.manage')->name('access.index');
    Route::put('/access', [OutletAccessController::class, 'update'])->middleware('can:restaurant.access.manage')->name('access.update');
});
