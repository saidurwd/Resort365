<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\DashboardController;
use Modules\Reports\Http\Controllers\ExportController;
use Modules\Reports\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Reports web routes
|--------------------------------------------------------------------------
|
| The management dashboard is the tenant's home page (/dashboard, named `dashboard`; every signed-in person gets it, each part
| showing only what their role may see). Reports are under /reports with names `reports.*`.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    Route::prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/', [ReportController::class, 'index'])->middleware('can:reports.dashboard.view')->name('index');
        Route::middleware('can:reports.report.export')->group(function (): void {
            Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
            Route::get('/exports/{export}/download', [ExportController::class, 'download'])->whereNumber('export')->name('exports.download');
        });
        Route::get('/{key}', [ReportController::class, 'show'])->where('key', '[a-z-]+')->middleware('throttle:reports')->name('show');
    });
});
