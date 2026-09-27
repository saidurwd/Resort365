<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\Auth\PlatformLoginController;
use Modules\Platform\Http\Controllers\ConsoleController;

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
        Route::post('/logout', [PlatformLoginController::class, 'destroy'])->name('logout');
    });
});
