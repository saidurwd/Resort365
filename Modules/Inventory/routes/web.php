<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\AdjustmentController;
use Modules\Inventory\Http\Controllers\CategoryController;
use Modules\Inventory\Http\Controllers\CountController;
use Modules\Inventory\Http\Controllers\DirectIssueController;
use Modules\Inventory\Http\Controllers\ImportController;
use Modules\Inventory\Http\Controllers\ItemController;
use Modules\Inventory\Http\Controllers\MovementController;
use Modules\Inventory\Http\Controllers\OpeningBalanceController;
use Modules\Inventory\Http\Controllers\ReportController;
use Modules\Inventory\Http\Controllers\RequisitionController;
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

    Route::middleware('can:inventory.requisition.view')->group(function (): void {
        Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
    });
    Route::middleware('can:inventory.requisition.create')->group(function (): void {
        Route::get('/requisitions/new', [RequisitionController::class, 'create'])->name('requisitions.create');
        Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
    });
    Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])->middleware('can:inventory.requisition.view')->whereNumber('requisition')->name('requisitions.show');
    Route::post('/requisitions/{requisition}/issue', [RequisitionController::class, 'issue'])->middleware('can:inventory.requisition.issue')->whereNumber('requisition')->name('requisitions.issue');
    Route::post('/requisitions/{requisition}/withdraw', [RequisitionController::class, 'withdraw'])->middleware('can:inventory.requisition.view')->whereNumber('requisition')->name('requisitions.withdraw');

    Route::middleware('can:inventory.issue.create')->group(function (): void {
        Route::get('/issues/new', [DirectIssueController::class, 'create'])->name('issues.create');
        Route::post('/issues', [DirectIssueController::class, 'store'])->name('issues.store');
    });

    Route::middleware('can:inventory.count.view')->group(function (): void {
        Route::get('/counts', [CountController::class, 'index'])->name('counts.index');
        Route::get('/counts/{count}', [CountController::class, 'show'])->whereNumber('count')->name('counts.show');
        Route::get('/counts/{count}/sheet', [CountController::class, 'sheet'])->whereNumber('count')->name('counts.sheet');
    });
    Route::middleware('can:inventory.count.manage')->group(function (): void {
        Route::post('/counts', [CountController::class, 'store'])->name('counts.store');
        Route::put('/counts/{count}', [CountController::class, 'save'])->whereNumber('count')->name('counts.save');
        Route::post('/counts/{count}/complete', [CountController::class, 'complete'])->whereNumber('count')->name('counts.complete');
        Route::post('/counts/{count}/cancel', [CountController::class, 'cancel'])->whereNumber('count')->name('counts.cancel');
    });

    Route::middleware('can:inventory.report.view')->group(function (): void {
        foreach (ReportController::REPORTS as $report) {
            Route::get('/reports/'.$report, [ReportController::class, 'show'])->defaults('report', $report)->name('reports.'.$report);
        }
    });

    Route::middleware('can:inventory.item.import')->group(function (): void {
        Route::get('/import', [ImportController::class, 'create'])->name('import.create');
        Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    });
});
