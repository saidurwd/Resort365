<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\PaymentController;

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
});
