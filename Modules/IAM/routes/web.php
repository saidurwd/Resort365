<?php

use Illuminate\Support\Facades\Route;
use Modules\IAM\Http\Controllers\InvitationController;
use Modules\IAM\Http\Controllers\ProfileController;
use Modules\IAM\Http\Controllers\RoleController;
use Modules\IAM\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| IAM web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /iam; route names follow `iam.resource.action`.
| Sign-in, password reset, email verification and 2FA endpoints come from
| Laravel Fortify (config/fortify.php). Permission checks: `can:` middleware
| here, record-level rules in UserPolicy / RolePolicy.
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
        Route::put('/profile/pos-pin', [ProfileController::class, 'updatePosPin'])->middleware('throttle:10,1')->name('profile.pos-pin.update');

        Route::middleware('can:iam.user.view')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
        });
        Route::post('/users', [UserController::class, 'store'])->middleware('can:iam.user.invite')->name('users.store');
        Route::middleware('can:iam.user.update')->group(function (): void {
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
            Route::post('/users/{user}/invitation', [UserController::class, 'resendInvitation'])->name('users.invitation.resend');
            Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
            Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        });

        Route::get('/roles', [RoleController::class, 'index'])->middleware('can:iam.role.view')->name('roles.index');
        Route::middleware('can:iam.role.create')->group(function (): void {
            Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        });
        Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('can:iam.role.view')->name('roles.show');
        Route::middleware('can:iam.role.update')->group(function (): void {
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:iam.role.delete')->name('roles.destroy');
    });
});
