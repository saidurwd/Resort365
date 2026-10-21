<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\AnnouncementController;
use Modules\Platform\Http\Controllers\AuditController;
use Modules\Platform\Http\Controllers\Auth\PlatformLoginController;
use Modules\Platform\Http\Controllers\ConsoleController;
use Modules\Platform\Http\Controllers\InvoiceController;
use Modules\Platform\Http\Controllers\PlanController;
use Modules\Platform\Http\Controllers\SignupController;
use Modules\Platform\Http\Controllers\SubscriptionController;
use Modules\Platform\Http\Controllers\TenantController;
use Modules\Platform\Http\Controllers\TenantExportController;

/*
|--------------------------------------------------------------------------
| Platform web routes (central domain)
|--------------------------------------------------------------------------
|
| Platform super admins sign in with the separate `platform` guard.
| Route names follow `platform.resource.action`.
|
*/

// Public sign-up (Step 7.4): a company starts its free trial on its own subdomain.
Route::prefix('signup')->name('platform.signup.')->controller(SignupController::class)->group(function (): void {
    Route::get('/', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:signup')->name('store');
    Route::get('/check', 'check')->middleware('throttle:60,1')->name('check');
});

Route::prefix('platform')->name('platform.')->group(function (): void {
    Route::middleware('guest:platform')->group(function (): void {
        Route::get('/login', [PlatformLoginController::class, 'create'])->name('login');
        Route::post('/login', [PlatformLoginController::class, 'store'])->middleware('throttle:platform-login')->name('login.store');
    });

    Route::middleware('auth:platform')->group(function (): void {
        Route::get('/', ConsoleController::class)->name('console');

        Route::prefix('tenants')->name('tenants.')->controller(TenantController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/{tenant}', 'show')->whereNumber('tenant')->name('show');
            Route::put('/{tenant}/plan', 'changePlan')->whereNumber('tenant')->name('plan');
            Route::post('/{tenant}/suspend', 'suspend')->whereNumber('tenant')->name('suspend');
            Route::post('/{tenant}/reactivate', 'reactivate')->whereNumber('tenant')->name('reactivate');
            Route::post('/{tenant}/impersonate', 'impersonate')->whereNumber('tenant')->name('impersonate');
        });

        // Subscriptions, platform invoices and data exports (Step 7.4).
        Route::prefix('tenants/{tenant}')->whereNumber('tenant')->name('tenants.')->group(function (): void {
            Route::post('/subscription', [SubscriptionController::class, 'start'])->name('subscription.start');
            Route::put('/subscription', [SubscriptionController::class, 'change'])->name('subscription.change');
            Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
            Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])->name('subscription.resume');
            Route::post('/close', [SubscriptionController::class, 'close'])->name('close');
            Route::post('/exports', [TenantExportController::class, 'store'])->name('exports.store');
            Route::get('/exports/{export}', [TenantExportController::class, 'download'])->whereNumber('export')->name('exports.download');
        });
        Route::prefix('invoices')->name('invoices.')->controller(InvoiceController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/{invoice}', 'show')->whereNumber('invoice')->name('show');
            Route::get('/{invoice}/pdf', 'pdf')->whereNumber('invoice')->name('pdf');
            Route::post('/{invoice}/payments', 'pay')->whereNumber('invoice')->name('pay');
            Route::post('/{invoice}/void', 'void')->whereNumber('invoice')->name('void');
        });

        Route::resource('plans', PlanController::class)->except('show')->parameters(['plans' => 'plan']);
        Route::resource('announcements', AnnouncementController::class)->except('show')->parameters(['announcements' => 'announcement']);
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::post('/logout', [PlatformLoginController::class, 'destroy'])->name('logout');
    });
});
