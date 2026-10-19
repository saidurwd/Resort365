<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\EmployeeSalaryController;
use Modules\Payroll\Http\Controllers\SalaryComponentController;
use Modules\Payroll\Http\Controllers\SalaryStructureController;
use Modules\Payroll\Http\Controllers\TaxTableController;

/*
|--------------------------------------------------------------------------
| Payroll web routes
|--------------------------------------------------------------------------
|
| URLs are prefixed with /payroll and route names follow `payroll.resource.action`. Employees are HR's, so the salary routes
| carry the employee's id, not a model.
|
*/

Route::prefix('payroll')->name('payroll.')->middleware(['auth', 'verified'])->group(function (): void {
    $write = ['create', 'store', 'edit', 'update', 'destroy'];

    Route::resource('components', SalaryComponentController::class)->parameters(['components' => 'component'])->only($write)->middleware('can:payroll.component.manage');
    Route::get('/components', [SalaryComponentController::class, 'index'])->middleware('can:payroll.component.view')->name('components.index');

    Route::resource('structures', SalaryStructureController::class)->parameters(['structures' => 'structure'])->only($write)->middleware('can:payroll.structure.manage');
    Route::get('/structures', [SalaryStructureController::class, 'index'])->middleware('can:payroll.structure.view')->name('structures.index');
    Route::get('/structures/{structure}', [SalaryStructureController::class, 'show'])->middleware('can:payroll.structure.view')->name('structures.show');

    Route::resource('tax-tables', TaxTableController::class)->parameters(['tax-tables' => 'taxTable'])->only($write)->middleware('can:payroll.tax.manage');
    Route::get('/tax-tables', [TaxTableController::class, 'index'])->middleware('can:payroll.tax.view')->name('tax-tables.index');

    Route::middleware('can:payroll.salary.manage')->group(function (): void {
        Route::post('/salaries/{employee}', [EmployeeSalaryController::class, 'store'])->whereNumber('employee')->name('salaries.store');
        Route::delete('/salaries/{employee}/{salary}', [EmployeeSalaryController::class, 'destroy'])->whereNumber('employee')->name('salaries.destroy');
    });
    Route::middleware('can:payroll.salary.view')->group(function (): void {
        Route::get('/salaries', [EmployeeSalaryController::class, 'index'])->name('salaries.index');
        Route::get('/salaries/{employee}', [EmployeeSalaryController::class, 'show'])->whereNumber('employee')->name('salaries.show');
    });
});
