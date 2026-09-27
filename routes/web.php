<?php

use App\Http\Controllers\UiKitController;
use App\Http\Middleware\EnsureLocalEnvironment;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('central.home');

// Component showcase for visual checks; 404 outside APP_ENV=local.
Route::middleware(EnsureLocalEnvironment::class)
    ->prefix('ui-kit')
    ->name('ui-kit.')
    ->controller(UiKitController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/print', 'print')->name('print');
        Route::get('/datatable', 'datatable')->name('datatable');
    });
