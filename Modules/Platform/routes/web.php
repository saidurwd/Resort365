<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\AnnouncementController;
use Modules\Platform\Http\Controllers\AuditController;
use Modules\Platform\Http\Controllers\Auth\PlatformLoginController;
use Modules\Platform\Http\Controllers\ConsoleController;
use Modules\Platform\Http\Controllers\PlanController;
use Modules\Platform\Http\Controllers\TenantController;

/*
|--------------------------------------------------------------------------
| Platform web routes (central domain)
|--------------------------------------------------------------------------
|
| Platform super admins sign in with the separate `platform` guard.
| Route names follow `platform.resource.action`.
|
*/

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

        Route::resource('plans', PlanController::class)->except('show')->parameters(['plans' => 'plan']);
        Route::resource('announcements', AnnouncementController::class)->except('show')->parameters(['announcements' => 'announcement']);
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::post('/logout', [PlatformLoginController::class, 'destroy'])->name('logout');
    });
});
