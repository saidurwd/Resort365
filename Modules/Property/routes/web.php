<?php

use Illuminate\Support\Facades\Route;
use Modules\Property\Http\Controllers\AmenityController;
use Modules\Property\Http\Controllers\CottageController;
use Modules\Property\Http\Controllers\CottageTypeController;
use Modules\Property\Http\Controllers\DepartmentController;
use Modules\Property\Http\Controllers\PropertyAccessController;
use Modules\Property\Http\Controllers\PropertyController;
use Modules\Property\Http\Controllers\RoomController;
use Modules\Property\Http\Controllers\RoomTypeController;
use Modules\Property\Http\Controllers\SwitchPropertyController;

/*
|--------------------------------------------------------------------------
| Property web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /property; route names follow `property.resource.action`.
| Properties outside the user's access never bind (404), nor do their cottages, rooms or types.
| "manage" routes are registered before "view" routes, so /create is not taken for a {record}.
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

    $write = ['create', 'store', 'edit', 'update', 'destroy'];

    Route::middleware('can:property.cottage.manage')->group(function () use ($write): void {
        Route::resource('cottage-types', CottageTypeController::class)->only($write);
        Route::resource('cottages', CottageController::class)->only($write);
    });
    Route::middleware('can:property.cottage.view')->group(function (): void {
        Route::get('/cottage-types', [CottageTypeController::class, 'index'])->name('cottage-types.index');
        Route::get('/cottages/data', [CottageController::class, 'data'])->name('cottages.data');
        Route::resource('cottages', CottageController::class)->only(['index', 'show']);
    });

    Route::middleware('can:property.room.manage')->group(function () use ($write): void {
        Route::resource('room-types', RoomTypeController::class)->only($write);
        Route::resource('rooms', RoomController::class)->only($write);
    });
    Route::middleware('can:property.room.view')->group(function (): void {
        Route::get('/room-types', [RoomTypeController::class, 'index'])->name('room-types.index');
        Route::get('/rooms/data', [RoomController::class, 'data'])->name('rooms.data');
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    });

    Route::resource('amenities', AmenityController::class)->only($write)->middleware('can:property.amenity.manage');
    Route::get('/amenities', [AmenityController::class, 'index'])->middleware('can:property.amenity.view')->name('amenities.index');

    Route::resource('departments', DepartmentController::class)->only($write)->middleware('can:property.department.manage');
    Route::get('/departments', [DepartmentController::class, 'index'])->middleware('can:property.department.view')->name('departments.index');
});
