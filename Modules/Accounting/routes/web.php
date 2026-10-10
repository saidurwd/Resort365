<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountController;
use Modules\Accounting\Http\Controllers\AccountMappingController;
use Modules\Accounting\Http\Controllers\BankAccountController;
use Modules\Accounting\Http\Controllers\ChequeController;
use Modules\Accounting\Http\Controllers\FiscalPeriodController;
use Modules\Accounting\Http\Controllers\JournalEntryController;
use Modules\Accounting\Http\Controllers\ReconciliationController;
use Modules\Accounting\Http\Controllers\TransferController;
use Modules\Accounting\Http\Controllers\VoucherController;

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

    Route::prefix('vouchers')->name('vouchers.')->controller(VoucherController::class)->group(function (): void {
        Route::middleware('can:accounting.voucher.view')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/data', 'data')->name('data');
        });
        Route::middleware('can:accounting.voucher.create')->group(function (): void {
            Route::get('/new/{type}', 'create')->whereIn('type', ['income', 'expense'])->name('create');
            Route::post('/', 'store')->name('store');
        });
        Route::get('/{voucher}', 'show')->middleware('can:accounting.voucher.view')->whereNumber('voucher')->name('show');
        Route::post('/{voucher}/void', 'void')->middleware('can:accounting.voucher.void')->whereNumber('voucher')->name('void');
    });

    Route::get('/banks', [BankAccountController::class, 'index'])->middleware('can:accounting.bank.view')->name('banks.index');
    Route::middleware('can:accounting.bank.manage')->group(function (): void {
        Route::post('/banks', [BankAccountController::class, 'store'])->name('banks.store');
        Route::put('/banks/{bank}', [BankAccountController::class, 'update'])->whereNumber('bank')->name('banks.update');
    });

    Route::prefix('transfers')->name('transfers.')->controller(TransferController::class)->group(function (): void {
        Route::middleware('can:accounting.bank.view')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/data', 'data')->name('data');
        });
        Route::middleware('can:accounting.bank.manage')->group(function (): void {
            Route::get('/new', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/{transfer}/void', 'void')->whereNumber('transfer')->name('void');
        });
        Route::get('/{transfer}', 'show')->middleware('can:accounting.bank.view')->whereNumber('transfer')->name('show');
    });

    Route::get('/cheques', [ChequeController::class, 'index'])->middleware('can:accounting.bank.view')->name('cheques.index');
    Route::get('/cheques/data', [ChequeController::class, 'data'])->middleware('can:accounting.bank.view')->name('cheques.data');
    Route::post('/cheques/{voucher}/bounce', [ChequeController::class, 'bounce'])->middleware('can:accounting.bank.manage')->whereNumber('voucher')->name('cheques.bounce');

    Route::prefix('reconciliation')->name('reconciliation.')->controller(ReconciliationController::class)->group(function (): void {
        Route::middleware('can:accounting.bank.view')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/statements/{statement}', 'show')->whereNumber('statement')->name('show');
            Route::get('/statements/{statement}/report', 'report')->whereNumber('statement')->name('report');
        });
        Route::post('/statements', 'import')->middleware('can:accounting.bank.manage')->name('import');
        Route::middleware('can:accounting.bank.reconcile')->group(function (): void {
            Route::post('/matches', 'match')->name('match');
            Route::delete('/matches/{match}', 'unmatch')->whereNumber('match')->name('unmatch');
            Route::post('/statements/{statement}/suggest', 'suggest')->whereNumber('statement')->name('suggest');
            Route::post('/statements/{statement}/complete', 'complete')->whereNumber('statement')->name('complete');
        });
    });

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
