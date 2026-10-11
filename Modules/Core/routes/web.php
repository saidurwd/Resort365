<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\ApprovalController;
use Modules\Core\Http\Controllers\ApprovalDelegationController;
use Modules\Core\Http\Controllers\ApprovalWorkflowController;
use Modules\Core\Http\Controllers\AttachmentController;
use Modules\Core\Http\Controllers\AuditLogController;
use Modules\Core\Http\Controllers\DocumentSequenceController;
use Modules\Core\Http\Controllers\NotificationController;
use Modules\Core\Http\Controllers\NotificationTemplateController;
use Modules\Core\Http\Controllers\SettingsController;
use Modules\Core\Http\Controllers\TaxController;

/*
|--------------------------------------------------------------------------
| Core web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /core; route names follow `core.resource.action`.
|
*/

Route::prefix('core')->name('core.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('can:core.approval.view')->group(function (): void {
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->whereNumber('approval')->name('approvals.show');
        Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->whereNumber('approval')->name('approvals.approve');
        Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->whereNumber('approval')->name('approvals.reject');
        Route::post('/approvals/{approval}/cancel', [ApprovalController::class, 'cancel'])->whereNumber('approval')->name('approvals.cancel');
        Route::post('/approval-delegations', [ApprovalDelegationController::class, 'store'])->name('approval-delegations.store');
        Route::delete('/approval-delegations/{delegation}', [ApprovalDelegationController::class, 'destroy'])->whereNumber('delegation')->name('approval-delegations.destroy');
    });
    Route::middleware('can:core.approval.manage')->group(function (): void {
        Route::get('/approval-workflows', [ApprovalWorkflowController::class, 'index'])->name('approval-workflows.index');
        Route::get('/approval-workflows/{workflow}/edit', [ApprovalWorkflowController::class, 'edit'])->whereNumber('workflow')->name('approval-workflows.edit');
        Route::put('/approval-workflows/{workflow}', [ApprovalWorkflowController::class, 'update'])->whereNumber('workflow')->name('approval-workflows.update');
    });

    Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:core.setting.view')->name('settings.index');
    Route::put('/settings/{group}', [SettingsController::class, 'update'])->middleware('can:core.setting.update')->name('settings.update');

    Route::get('/document-sequences', [DocumentSequenceController::class, 'index'])->middleware('can:core.sequence.view')->name('sequences.index');
    Route::middleware('can:core.sequence.update')->group(function (): void {
        Route::get('/document-sequences/{type}/edit', [DocumentSequenceController::class, 'edit'])->name('sequences.edit');
        Route::put('/document-sequences/{type}', [DocumentSequenceController::class, 'update'])->name('sequences.update');
    });

    Route::middleware('can:core.tax.manage')->group(function (): void {
        Route::get('/taxes/create', [TaxController::class, 'create'])->name('taxes.create');
        Route::post('/taxes', [TaxController::class, 'store'])->name('taxes.store');
        Route::get('/taxes/{tax}/edit', [TaxController::class, 'edit'])->name('taxes.edit');
        Route::put('/taxes/{tax}', [TaxController::class, 'update'])->name('taxes.update');
        Route::delete('/taxes/{tax}', [TaxController::class, 'destroy'])->name('taxes.destroy');
        Route::get('/tax-categories/create', [TaxController::class, 'createCategory'])->name('tax-categories.create');
        Route::post('/tax-categories', [TaxController::class, 'storeCategory'])->name('tax-categories.store');
        Route::get('/tax-categories/{tax_category}/edit', [TaxController::class, 'editCategory'])->name('tax-categories.edit');
        Route::put('/tax-categories/{tax_category}', [TaxController::class, 'updateCategory'])->name('tax-categories.update');
    });
    Route::get('/taxes', [TaxController::class, 'index'])->middleware('can:core.tax.view')->name('taxes.index');

    Route::get('/notification-templates', [NotificationTemplateController::class, 'index'])->middleware('can:core.notification-template.view')->name('notification-templates.index');
    Route::middleware('can:core.notification-template.manage')->group(function (): void {
        Route::get('/notification-templates/{channel}/{key}/edit', [NotificationTemplateController::class, 'edit'])->name('notification-templates.edit');
        Route::put('/notification-templates/{channel}/{key}', [NotificationTemplateController::class, 'update'])->name('notification-templates.update');
        Route::delete('/notification-templates/{channel}/{key}', [NotificationTemplateController::class, 'destroy'])->name('notification-templates.destroy');
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
