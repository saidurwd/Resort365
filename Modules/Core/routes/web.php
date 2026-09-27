<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AttachmentController;
use Modules\Core\Http\Controllers\AuditLogController;
use Modules\Core\Http\Controllers\DocumentSequenceController;
use Modules\Core\Http\Controllers\NotificationController;
use Modules\Core\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Core web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /core; route names follow `core.resource.action`.
|
*/

Route::prefix('core')->name('core.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:core.setting.view')->name('settings.index');
    Route::put('/settings/{group}', [SettingsController::class, 'update'])->middleware('can:core.setting.update')->name('settings.update');

    Route::get('/document-sequences', [DocumentSequenceController::class, 'index'])->middleware('can:core.sequence.view')->name('sequences.index');
    Route::middleware('can:core.sequence.update')->group(function (): void {
        Route::get('/document-sequences/{type}/edit', [DocumentSequenceController::class, 'edit'])->name('sequences.edit');
        Route::put('/document-sequences/{type}', [DocumentSequenceController::class, 'update'])->name('sequences.update');
    });

    Route::middleware('can:core.audit.view')->group(function (): void {
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit-log/data', [AuditLogController::class, 'data'])->name('audit.data');
    });

    // Authorization against the attached record's policy happens in the controller.
    Route::post('/attachments/{type}/{id}', [AttachmentController::class, 'store'])->middleware('throttle:30,1')->name('attachments.store');
    Route::get('/attachments/{media}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{media}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});
