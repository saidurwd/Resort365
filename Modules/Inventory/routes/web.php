<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\AdjustmentController;
use Modules\Inventory\Http\Controllers\CategoryController;
use Modules\Inventory\Http\Controllers\ImportController;
use Modules\Inventory\Http\Controllers\ItemController;
use Modules\Inventory\Http\Controllers\MovementController;
use Modules\Inventory\Http\Controllers\OpeningBalanceController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\StoreController;
use Modules\Inventory\Http\Controllers\TransferController;
use Modules\Inventory\Http\Controllers\UnitController;

/*
|--------------------------------------------------------------------------
| Inventory web routes
|--------------------------------------------------------------------------
|
| URLs are prefixed with /inventory and route names follow
| `inventory.resource.action`. Every route must be protected by
| authentication and permission middleware (see docs/MODULE_GUIDE.md).
|
*/

Route::prefix('inventory')->name('inventory.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('can:inventory.item.view')->group(function (): void {
        Route::get('/items', [ItemController::class, 'index'])->name('items.index');
        Route::get('/items/data', [ItemController::class, 'data'])->name('items.data');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    });

    Route::middleware('can:inventory.item.manage')->group(function (): void {
        Route::get('/items/new', [ItemController::class, 'create'])->name('items.create');
        Route::post('/items', [ItemController::class, 'store'])->name('items.store');
        Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->whereNumber('item')->name('items.edit');
        Route::put('/items/{item}', [ItemController::class, 'update'])->whereNumber('item')->name('items.update');
    });

    Route::middleware('can:inventory.catalog.manage')->group(function (): void {
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('categories.update');
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->whereNumber('unit')->name('units.update');
    });

    Route::get('/stores', [StoreController::class, 'index'])->middleware('can:inventory.store.view')->name('stores.index');
    Route::middleware('can:inventory.store.manage')->group(function (): void {
        Route::post('/stores', [StoreController::class, 'store'])->name('stores.store');
        Route::put('/stores/{store}', [StoreController::class, 'update'])->whereNumber('store')->name('stores.update');
    });

    Route::middleware('can:inventory.stock.view')->group(function (): void {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
        Route::get('/movements/data', [MovementController::class, 'data'])->name('movements.data');
        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
    });

    Route::middleware('can:inventory.stock.adjust')->group(function (): void {
        Route::get('/adjustments/new', [AdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('/adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('/openings/new', [OpeningBalanceController::class, 'create'])->name('openings.create');
        Route::post('/openings', [OpeningBalanceController::class, 'store'])->name('openings.store');
        Route::post('/movements/{movement}/reverse', [MovementController::class, 'reverse'])->whereNumber('movement')->name('movements.reverse');
    });

    Route::middleware('can:inventory.transfer.manage')->group(function (): void {
        Route::get('/transfers/new', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive'])->whereNumber('transfer')->name('transfers.receive');
        Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->whereNumber('transfer')->name('transfers.cancel');
    });

    Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->middleware('can:inventory.stock.view')->whereNumber('transfer')->name('transfers.show');

    Route::middleware('can:inventory.item.import')->group(function (): void {
        Route::get('/import', [ImportController::class, 'create'])->name('import.create');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    });
});
