<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountController;
use Modules\Accounting\Http\Controllers\AccountMappingController;
use Modules\Accounting\Http\Controllers\FiscalPeriodController;
use Modules\Accounting\Http\Controllers\JournalEntryController;

/*
|--------------------------------------------------------------------------
| Accounting web routes
|--------------------------------------------------------------------------
|
| URLs are prefixed with /accounting and route names follow
| `accounting.resource.action`. Every route must be protected by
| authentication and permission middleware (see docs/MODULE_GUIDE.md).
|
*/

Route::prefix('accounting')->name('accounting.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/accounts', [AccountController::class, 'index'])->middleware('can:accounting.account.view')->name('accounts.index');
    Route::middleware('can:accounting.account.manage')->group(function (): void {
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::put('/accounts/{account}', [AccountController::class, 'update'])->whereNumber('account')->name('accounts.update');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->whereNumber('account')->name('accounts.destroy');
    });

    Route::get('/mappings', [AccountMappingController::class, 'index'])->middleware('can:accounting.account.view')->name('mappings.index');
    Route::put('/mappings', [AccountMappingController::class, 'update'])->middleware('can:accounting.account.manage')->name('mappings.update');

    Route::get('/periods', [FiscalPeriodController::class, 'index'])->middleware('can:accounting.period.view')->name('periods.index');
    Route::middleware('can:accounting.period.manage')->group(function (): void {
        Route::post('/periods', [FiscalPeriodController::class, 'store'])->name('periods.store');
        Route::put('/periods/{period}', [FiscalPeriodController::class, 'update'])->whereNumber('period')->name('periods.update');
    });

    Route::prefix('journals')->name('journals.')->controller(JournalEntryController::class)->group(function (): void {
        Route::middleware('can:accounting.journal.view')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/data', 'data')->name('data');
        });
        Route::middleware('can:accounting.journal.create')->group(function (): void {
            Route::get('/new', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{entry}/edit', 'edit')->whereNumber('entry')->name('edit');
            Route::put('/{entry}', 'update')->whereNumber('entry')->name('update');
            Route::delete('/{entry}', 'destroy')->whereNumber('entry')->name('destroy');
        });
        Route::get('/{entry}', 'show')->middleware('can:accounting.journal.view')->whereNumber('entry')->name('show');
        Route::post('/{entry}/post', 'post')->middleware('can:accounting.journal.post')->whereNumber('entry')->name('post');
        Route::post('/{entry}/reverse', 'reverse')->middleware('can:accounting.journal.reverse')->whereNumber('entry')->name('reverse');
    });
});
