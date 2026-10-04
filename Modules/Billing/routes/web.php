<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\ChargeCodeController;
use Modules\Billing\Http\Controllers\CityLedgerController;
use Modules\Billing\Http\Controllers\ExtraServiceController;
use Modules\Billing\Http\Controllers\FolioController;
use Modules\Billing\Http\Controllers\InvoiceController;
use Modules\Billing\Http\Controllers\PaymentController;
use Modules\Billing\Http\Controllers\RefundController;

/*
|--------------------------------------------------------------------------
| Billing web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /billing; route names follow `billing.resource.action`.
| Payments are taken from a reservation's Payments tab.
|
*/

Route::prefix('billing')->name('billing.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('can:billing.payment.create')->name('payments.store');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->middleware('can:billing.payment.view')->name('payments.receipt');

    // Folios (from the reservation page's Folios tab).
    Route::middleware('can:billing.folio.post')->group(function (): void {
        Route::post('/folios', [FolioController::class, 'store'])->name('folios.store');
        Route::post('/folios/{folio}/charges', [FolioController::class, 'charge'])->name('folios.charge');
        Route::post('/folio-routing', [FolioController::class, 'route'])->name('folios.route');
    });
    Route::post('/folios/{folio}/adjustments', [FolioController::class, 'adjust'])->middleware('can:billing.folio.adjust')->name('folios.adjust');
    Route::post('/folios/{folio}/void', [FolioController::class, 'void'])->middleware('can:billing.folio.void')->name('folios.void');

    // Check-out: refunds, invoices and credit notes, the city ledger.
    Route::post('/refunds', [RefundController::class, 'store'])->middleware('can:billing.refund.issue')->name('refunds.store');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('can:billing.invoice.view')->name('invoices.pdf');
    Route::post('/invoices/{invoice}/credit-notes', [InvoiceController::class, 'credit'])->middleware('can:billing.credit-note.issue')->name('invoices.credit');
    Route::get('/credit-notes/{creditNote}/pdf', [InvoiceController::class, 'creditNotePdf'])->middleware('can:billing.invoice.view')->name('credit-notes.pdf');
    Route::get('/city-ledger', [CityLedgerController::class, 'index'])->middleware('can:billing.city-ledger.view')->name('city-ledger.index');
    Route::post('/city-ledger/{entry}/payments', [CityLedgerController::class, 'receive'])->middleware('can:billing.city-ledger.manage')->name('city-ledger.receive');
    Route::post('/folios/{folio}/city-ledger', [CityLedgerController::class, 'transfer'])->middleware('can:billing.folio.post')->name('folios.transfer');

    // Setup: charge codes (tenant-wide) and the extras catalogue (current property).
    Route::get('/charge-codes', [ChargeCodeController::class, 'index'])->middleware('can:billing.charge-code.view')->name('charge-codes.index');
    Route::resource('charge-codes', ChargeCodeController::class)->except(['index', 'show'])->middleware('can:billing.charge-code.manage');
    Route::get('/extras', [ExtraServiceController::class, 'index'])->middleware('can:billing.extra-service.view')->name('extra-services.index');
    Route::resource('extras', ExtraServiceController::class)->except(['index', 'show'])->parameters(['extras' => 'extra_service'])
        ->names('extra-services')->middleware('can:billing.extra-service.manage');
});
