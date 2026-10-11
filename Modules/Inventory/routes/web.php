<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\CategoryController;
use Modules\Inventory\Http\Controllers\ImportController;
use Modules\Inventory\Http\Controllers\ItemController;
use Modules\Inventory\Http\Controllers\StoreController;
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

    Route::middleware('can:inventory.item.import')->group(function (): void {
        Route::get('/import', [ImportController::class, 'create'])->name('import.create');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    });
});
