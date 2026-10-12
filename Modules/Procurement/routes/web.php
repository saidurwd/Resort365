<?php

use Illuminate\Support\Facades\Route;
use Modules\Procurement\Http\Controllers\BillController;
use Modules\Procurement\Http\Controllers\OrderController;
use Modules\Procurement\Http\Controllers\PaymentController;
use Modules\Procurement\Http\Controllers\ReceiptController;
use Modules\Procurement\Http\Controllers\RequisitionController;
use Modules\Procurement\Http\Controllers\ReturnController;
use Modules\Procurement\Http\Controllers\RfqController;
use Modules\Procurement\Http\Controllers\SuggestionController;
use Modules\Procurement\Http\Controllers\VendorController;

/*
|--------------------------------------------------------------------------
| Procurement web routes
|--------------------------------------------------------------------------
|
| URLs are prefixed with /procurement and route names follow
| `procurement.resource.action`. Every route must be protected by
| authentication and permission middleware (see docs/MODULE_GUIDE.md).
|
*/

Route::prefix('procurement')->name('procurement.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('can:procurement.vendor.view')->group(function (): void {
        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    });
    Route::middleware('can:procurement.vendor.manage')->group(function (): void {
        Route::get('/vendors/new', [VendorController::class, 'create'])->name('vendors.create');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::get('/vendors/{vendor}/edit', [VendorController::class, 'edit'])->whereNumber('vendor')->name('vendors.edit');
        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->whereNumber('vendor')->name('vendors.update');
        Route::post('/vendors/{vendor}/contacts', [VendorController::class, 'addContact'])->whereNumber('vendor')->name('vendors.contacts.store');
        Route::delete('/vendors/{vendor}/contacts/{contact}', [VendorController::class, 'removeContact'])->whereNumber(['vendor', 'contact'])->name('vendors.contacts.destroy');
        Route::post('/vendors/{vendor}/prices', [VendorController::class, 'addPrice'])->whereNumber('vendor')->name('vendors.prices.store');
        Route::delete('/vendors/{vendor}/prices/{entry}', [VendorController::class, 'removePrice'])->whereNumber(['vendor', 'entry'])->name('vendors.prices.destroy');
    });
    Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->middleware('can:procurement.vendor.view')->whereNumber('vendor')->name('vendors.show');

    Route::middleware('can:procurement.requisition.view')->group(function (): void {
        Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
        Route::get('/suggestions', [SuggestionController::class, 'index'])->name('suggestions.index');
    });
    Route::middleware('can:procurement.requisition.create')->group(function (): void {
        Route::get('/requisitions/new', [RequisitionController::class, 'create'])->name('requisitions.create');
        Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
        Route::post('/suggestions', [SuggestionController::class, 'store'])->name('suggestions.store');
    });
    Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])->middleware('can:procurement.requisition.view')->whereNumber('requisition')->name('requisitions.show');
    Route::post('/requisitions/{requisition}/withdraw', [RequisitionController::class, 'withdraw'])->middleware('can:procurement.requisition.view')->whereNumber('requisition')->name('requisitions.withdraw');

    Route::middleware('can:procurement.rfq.view')->group(function (): void {
        Route::get('/rfqs', [RfqController::class, 'index'])->name('rfqs.index');
        Route::get('/rfqs/{rfq}', [RfqController::class, 'show'])->whereNumber('rfq')->name('rfqs.show');
    });
    Route::middleware('can:procurement.rfq.manage')->group(function (): void {
        Route::post('/rfqs', [RfqController::class, 'store'])->name('rfqs.store');
        Route::post('/rfqs/{rfq}/send', [RfqController::class, 'send'])->whereNumber('rfq')->name('rfqs.send');
        Route::post('/rfqs/{rfq}/quotations', [RfqController::class, 'quote'])->whereNumber('rfq')->name('rfqs.quote');
        Route::post('/rfqs/{rfq}/award', [RfqController::class, 'award'])->whereNumber('rfq')->name('rfqs.award');
        Route::post('/rfqs/{rfq}/cancel', [RfqController::class, 'cancel'])->whereNumber('rfq')->name('rfqs.cancel');
    });

    Route::middleware('can:procurement.order.view')->group(function (): void {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    });
    Route::middleware('can:procurement.order.create')->group(function (): void {
        Route::get('/orders/new', [OrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::post('/orders/from-requisition', [OrderController::class, 'fromRequisition'])->name('orders.from-requisition');
        Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->whereNumber('order')->name('orders.edit');
        Route::put('/orders/{order}', [OrderController::class, 'update'])->whereNumber('order')->name('orders.update');
        Route::post('/orders/{order}/submit', [OrderController::class, 'submit'])->whereNumber('order')->name('orders.submit');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->name('orders.cancel');
    });
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('can:procurement.order.view')->whereNumber('order')->name('orders.show');
    Route::get('/orders/{order}/pdf', [OrderController::class, 'pdf'])->middleware('can:procurement.order.view')->whereNumber('order')->name('orders.pdf');
    Route::post('/orders/{order}/send', [OrderController::class, 'send'])->middleware('can:procurement.order.send')->whereNumber('order')->name('orders.send');

    Route::middleware('can:procurement.receipt.view')->group(function (): void {
        Route::get('/receipts', [ReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/receipts/{receipt}', [ReceiptController::class, 'show'])->whereNumber('receipt')->name('receipts.show');
    });
    Route::middleware('can:procurement.receipt.create')->group(function (): void {
        Route::get('/orders/{order}/receive', [ReceiptController::class, 'create'])->whereNumber('order')->name('receipts.create');
        Route::post('/orders/{order}/receive', [ReceiptController::class, 'store'])->whereNumber('order')->name('receipts.store');
    });

    Route::middleware('can:procurement.bill.view')->group(function (): void {
        Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    });
    Route::middleware('can:procurement.bill.create')->group(function (): void {
        Route::get('/bills/new', [BillController::class, 'create'])->name('bills.create');
        Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
        Route::get('/bills/{bill}/edit', [BillController::class, 'edit'])->whereNumber('bill')->name('bills.edit');
        Route::put('/bills/{bill}', [BillController::class, 'update'])->whereNumber('bill')->name('bills.update');
        Route::post('/bills/{bill}/submit', [BillController::class, 'submit'])->whereNumber('bill')->name('bills.submit');
        Route::post('/bills/{bill}/cancel', [BillController::class, 'cancel'])->whereNumber('bill')->name('bills.cancel');
    });
    Route::get('/bills/{bill}', [BillController::class, 'show'])->middleware('can:procurement.bill.view')->whereNumber('bill')->name('bills.show');

    Route::middleware('can:procurement.payment.create')->group(function (): void {
        Route::get('/payments/new', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('/payments/{payment}/allocate', [PaymentController::class, 'allocate'])->whereNumber('payment')->name('payments.allocate');
    });
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->middleware('can:procurement.bill.view')->whereNumber('payment')->name('payments.show');

    Route::get('/returns', [ReturnController::class, 'index'])->middleware('can:procurement.return.view')->name('returns.index');
    Route::middleware('can:procurement.return.create')->group(function (): void {
        Route::get('/receipts/{receipt}/return', [ReturnController::class, 'create'])->whereNumber('receipt')->name('returns.create');
        Route::post('/receipts/{receipt}/return', [ReturnController::class, 'store'])->whereNumber('receipt')->name('returns.store');
    });
    Route::get('/returns/{return}', [ReturnController::class, 'show'])->middleware('can:procurement.return.view')->whereNumber('return')->name('returns.show');
});
