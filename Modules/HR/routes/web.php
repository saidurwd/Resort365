<?php

use Illuminate\Support\Facades\Route;
use Modules\HR\Http\Controllers\AttendanceController;
use Modules\HR\Http\Controllers\AttendanceImportController;
use Modules\HR\Http\Controllers\AttendanceReportController;
use Modules\HR\Http\Controllers\DesignationController;
use Modules\HR\Http\Controllers\EmployeeController;
use Modules\HR\Http\Controllers\EmployeeLifecycleController;
use Modules\HR\Http\Controllers\GradeController;
use Modules\HR\Http\Controllers\HolidayController;
use Modules\HR\Http\Controllers\RosterController;
use Modules\HR\Http\Controllers\ShiftController;
use Modules\HR\Http\Requests\LifecycleRequest;
use Modules\HR\Http\Requests\RosterRequest;

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
    Route::resource('shifts', ShiftController::class)->only($write)->middleware('can:hr.shift.manage');
    Route::get('/shifts', [ShiftController::class, 'index'])->middleware('can:hr.shift.view')->name('shifts.index');

    Route::resource('holidays', HolidayController::class)->only($write)->middleware('can:hr.holiday.manage');
    Route::get('/holidays', [HolidayController::class, 'index'])->middleware('can:hr.holiday.view')->name('holidays.index');

    Route::get('/roster', [RosterController::class, 'index'])->middleware('can:hr.roster.view')->name('roster.index');
    Route::post('/roster/{action}', [RosterController::class, 'update'])->whereIn('action', RosterRequest::ACTIONS)->middleware('can:hr.roster.manage')->name('roster.update');

    Route::middleware('can:hr.attendance.import')->group(function (): void {
        Route::get('/attendance/import', [AttendanceImportController::class, 'create'])->name('attendance.import');
        Route::post('/attendance/import', [AttendanceImportController::class, 'store'])->name('attendance.import.store');
    });
    Route::middleware('can:hr.attendance.record')->group(function (): void {
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
    });
    Route::middleware('can:hr.attendance.view')->group(function (): void {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/report', [AttendanceReportController::class, 'show'])->name('attendance.report');
    });
});
