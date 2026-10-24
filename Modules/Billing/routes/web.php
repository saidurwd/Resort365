<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Modules\Billing\Http\Controllers\ChargeCodeController;
use Modules\Billing\Http\Controllers\CityLedgerController;
use Modules\Billing\Http\Controllers\ExtraServiceController;
use Modules\Billing\Http\Controllers\FolioController;
use Modules\Billing\Http\Controllers\GatewayController;
use Modules\Billing\Http\Controllers\InvoiceController;
use Modules\Billing\Http\Controllers\Online\GatewayReturnController;
use Modules\Billing\Http\Controllers\Online\PaymentLinkController as OnlinePaymentLinkController;
use Modules\Billing\Http\Controllers\PaymentController;
use Modules\Billing\Http\Controllers\PaymentLinkController;
use Modules\Billing\Http\Controllers\RefundController;
use Modules\Billing\Http\Controllers\ShiftController;

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
    // Online payments (Step 8.3): the property's gateways, and payment links from the booking's Payments tab.
    Route::get('/online-payments', [GatewayController::class, 'index'])->middleware('can:billing.gateway.manage')->name('gateways.index');
    Route::put('/online-payments/{provider}', [GatewayController::class, 'update'])->middleware('can:billing.gateway.manage')->name('gateways.update');
    Route::post('/reservations/{reservation}/payment-links', [PaymentLinkController::class, 'store'])->whereNumber('reservation')->middleware('can:billing.payment.link')->name('payment-links.store');

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

    // Cashier shifts: my shift (open, count and close), every shift with its variance, the report.
    Route::middleware('can:billing.shift.open')->group(function (): void {
        Route::get('/shifts/mine', [ShiftController::class, 'mine'])->name('shifts.mine');
        Route::post('/shifts', [ShiftController::class, 'open'])->name('shifts.open');
        Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    });
    Route::middleware('can:billing.shift.view')->group(function (): void {
        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
        Route::get('/shifts/data', [ShiftController::class, 'data'])->name('shifts.data');
    });
    Route::get('/shifts/{shift}', [ShiftController::class, 'show'])->whereNumber('shift')->name('shifts.show');

    // Setup: charge codes (tenant-wide) and the extras catalogue (current property).
    Route::get('/charge-codes', [ChargeCodeController::class, 'index'])->middleware('can:billing.charge-code.view')->name('charge-codes.index');
    Route::resource('charge-codes', ChargeCodeController::class)->except(['index', 'show'])->middleware('can:billing.charge-code.manage');
    Route::get('/extras', [ExtraServiceController::class, 'index'])->middleware('can:billing.extra-service.view')->name('extra-services.index');
    Route::resource('extras', ExtraServiceController::class)->except(['index', 'show'])->parameters(['extras' => 'extra_service'])
        ->names('extra-services')->middleware('can:billing.extra-service.manage');
});

// Guests paying online (Step 8.3), no sign-in. Gateways come back and notify without the session or CSRF checks: a cross-site POST
// must not replace the guest's session, and the adapters check signatures and ask the gateway instead.
Route::prefix('pay')->name('billing.')->group(function (): void {
    Route::match(['get', 'post'], '/return/{uuid}', [GatewayReturnController::class, 'back'])->whereUuid('uuid')
        ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:60,1')->name('online.return');
    Route::post('/notify/{provider}/{gateway}', [GatewayReturnController::class, 'notify'])->whereIn('provider', ['sslcommerz', 'bkash', 'stripe', 'test'])->whereNumber('gateway')
        ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->middleware('throttle:120,1')->name('online.notify');
    Route::get('/test/{uuid}', [GatewayReturnController::class, 'testPage'])->whereUuid('uuid')->middleware('signed')->name('online.test');
    Route::post('/test/{uuid}', [GatewayReturnController::class, 'testSubmit'])->whereUuid('uuid')->middleware('signed')->name('online.test.submit');
    Route::get('/link/{reservation}/{purpose}', [OnlinePaymentLinkController::class, 'show'])->whereNumber('reservation')->whereIn('purpose', ['deposit', 'balance'])->name('pay-link.show');
    Route::post('/link/{reservation}/{purpose}', [OnlinePaymentLinkController::class, 'start'])->whereNumber('reservation')->whereIn('purpose', ['deposit', 'balance'])->middleware('throttle:10,1')->name('pay-link.start');
});
