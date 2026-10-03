<?php

use Illuminate\Support\Facades\Route;
use Modules\Property\Http\Controllers\PropertyAccessController;
use Modules\Property\Http\Controllers\PropertyController;
use Modules\Property\Http\Controllers\SwitchPropertyController;

/*
|--------------------------------------------------------------------------
| Property web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /property; route names follow `property.resource.action`.
| Properties outside the user's access never bind (404).
|
*/

Route::prefix('property')->name('property.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::post('/switch/{property}', SwitchPropertyController::class)->name('switch');

    Route::get('/properties', [PropertyController::class, 'index'])->middleware('can:property.property.view')->name('properties.index');
    Route::middleware('can:property.property.create')->group(function (): void {
        Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
        Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
    });
    Route::middleware('can:property.property.update')->group(function (): void {
        Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])->name('properties.edit');
        Route::put('/properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
    });

    Route::middleware('can:property.access.update')->group(function (): void {
        Route::get('/access', [PropertyAccessController::class, 'index'])->name('access.index');
        Route::put('/access', [PropertyAccessController::class, 'update'])->name('access.update');
    });
});
