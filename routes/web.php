<?php

use App\Http\Controllers\ApiDocumentsController;
use App\Http\Controllers\UiKitController;
use App\Http\Middleware\EnsureLocalEnvironment;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('central.home');

// The REST API's documents (Step 8.1), generated from the code by Scramble: a reader and the OpenAPI 3.1 file.
Route::middleware('throttle:30,1')->controller(ApiDocumentsController::class)->group(function (): void {
    Route::get('/docs/api', 'show')->name('api-docs.ui');
    Route::get('/docs/api.json', 'json')->name('api-docs.json');
});

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
