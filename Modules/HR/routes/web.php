<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\DesignationController;
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Http\Controllers\EmployeeLifecycleController;
use Modules\HR\Http\Controllers\GradeController;
use Modules\HR\Http\Requests\LifecycleRequest;

/*
|--------------------------------------------------------------------------
| HR web routes
|--------------------------------------------------------------------------
|
| URLs are prefixed with /hr and route names follow `hr.resource.action`. "Write" routes are registered before
| {employee} routes, so /create is not taken for a record.
|
*/

Route::prefix('hr')->name('hr.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('can:hr.employee.manage')->group(function (): void {
        Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::put('/employees/{employee}/user', [EmployeeController::class, 'linkUser'])->name('employees.user');
    });

    Route::post('/employees/{employee}/lifecycle/{event}', [EmployeeLifecycleController::class, 'store'])->whereIn('event', LifecycleRequest::EVENTS)
        ->middleware('can:hr.lifecycle.manage')->name('employees.lifecycle');

    Route::middleware('can:hr.employee.view')->group(function (): void {
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/data', [EmployeeController::class, 'data'])->name('employees.data');
        Route::get('/employees/search', [EmployeeController::class, 'search'])->middleware('throttle:120,1')->name('employees.search');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    });

    $write = ['create', 'store', 'edit', 'update', 'destroy'];

    Route::resource('designations', DesignationController::class)->only($write)->middleware('can:hr.setup.manage');
    Route::get('/designations', [DesignationController::class, 'index'])->middleware('can:hr.employee.view')->name('designations.index');

    Route::resource('grades', GradeController::class)->only($write)->middleware('can:hr.setup.manage');
    Route::get('/grades', [GradeController::class, 'index'])->middleware('can:hr.employee.view')->name('grades.index');
});
