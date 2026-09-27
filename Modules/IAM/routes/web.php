<?php

use Illuminate\Support\Facades\Route;
use Modules\IAM\Http\Controllers\InvitationController;
use Modules\IAM\Http\Controllers\ProfileController;
use Modules\IAM\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| IAM web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /iam; route names follow `iam.resource.action`.
| Sign-in, password reset, email verification and 2FA endpoints come from
| Laravel Fortify (config/fortify.php). TODO(step-0.6): permission middleware.
|
*/

Route::prefix('iam')->name('iam.')->group(function (): void {
    Route::middleware(['guest', 'signed', 'throttle:6,1'])->group(function (): void {
        Route::get('/invitations/{user}', [InvitationController::class, 'show'])->name('invitations.show');
        Route::post('/invitations/{user}', [InvitationController::class, 'store'])->name('invitations.store');
    });

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('users.invitation.resend');
        Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    });
});
