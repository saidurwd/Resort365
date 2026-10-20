<?php

use Illuminate\Support\Facades\Route;
use Modules\Payroll\Http\Controllers\EmployeeSalaryController;
use Modules\Payroll\Http\Controllers\LoanController;
use Modules\Payroll\Http\Controllers\PayrollReportController;
use Modules\Payroll\Http\Controllers\PayrollRunController;
use Modules\Payroll\Http\Controllers\PayslipController;
use Modules\Payroll\Http\Controllers\PoolController;
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
    Route::middleware('can:payroll.run.create')->group(function (): void {
        Route::get('/runs/create', [PayrollRunController::class, 'create'])->name('runs.create');
        Route::post('/runs', [PayrollRunController::class, 'store'])->name('runs.store');
        Route::post('/runs/{run}/calculate', [PayrollRunController::class, 'calculate'])->name('runs.calculate');
        Route::post('/runs/{run}/submit', [PayrollRunController::class, 'submit'])->name('runs.submit');
        Route::delete('/runs/{run}', [PayrollRunController::class, 'destroy'])->name('runs.destroy');
        Route::post('/payslips/{payslip}/adjustments', [PayslipController::class, 'adjust'])->name('payslips.adjust');
        Route::delete('/payslips/{payslip}/adjustments/{line}', [PayslipController::class, 'removeAdjustment'])->name('payslips.adjustments.destroy');
    });
    Route::post('/runs/{run}/pay', [PayrollRunController::class, 'pay'])->middleware('can:payroll.run.pay')->name('runs.pay');
    Route::post('/runs/{run}/email', [PayrollRunController::class, 'email'])->middleware('can:payroll.payslip.email')->name('runs.email');
    Route::middleware('can:payroll.run.export')->group(function (): void {
        Route::get('/runs/{run}/register', [PayrollRunController::class, 'register'])->name('runs.register');
        Route::get('/runs/{run}/bank-file', [PayrollRunController::class, 'bankFile'])->name('runs.bank-file');
    });
    Route::middleware('can:payroll.run.view')->group(function (): void {
        Route::get('/runs', [PayrollRunController::class, 'index'])->name('runs.index');
        Route::get('/runs/{run}', [PayrollRunController::class, 'show'])->name('runs.show');
    });
    Route::middleware('can:payroll.payslip.view')->group(function (): void {
        Route::get('/payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
        Route::get('/payslips/{payslip}/pdf', [PayslipController::class, 'pdf'])->name('payslips.pdf');
    });

    Route::middleware('can:payroll.pool.manage')->group(function (): void {
        Route::get('/pools/create', [PoolController::class, 'create'])->name('pools.create');
        Route::post('/pools', [PoolController::class, 'store'])->name('pools.store');
        Route::post('/pools/participants', [PoolController::class, 'saveParticipants'])->name('pools.participants.save');
        Route::post('/pools/{pool}/recalculate', [PoolController::class, 'recalculate'])->name('pools.recalculate');
        Route::post('/pools/{pool}/finalise', [PoolController::class, 'finalise'])->name('pools.finalise');
        Route::post('/pools/{pool}/reopen', [PoolController::class, 'reopen'])->name('pools.reopen');
        Route::delete('/pools/{pool}', [PoolController::class, 'destroy'])->name('pools.destroy');
    });
    Route::middleware('can:payroll.pool.view')->group(function (): void {
        Route::get('/pools', [PoolController::class, 'index'])->name('pools.index');
        Route::get('/pools/participants', [PoolController::class, 'participants'])->name('pools.participants');
        Route::get('/pools/{pool}', [PoolController::class, 'show'])->name('pools.show');
    });
    Route::middleware('can:payroll.report.view')->prefix('reports')->name('reports.')->group(function (): void {
        Route::get('/', [PayrollReportController::class, 'index'])->name('index');
        Route::get('/cost', [PayrollReportController::class, 'cost'])->name('cost');
        Route::get('/festival-bonuses', [PayrollReportController::class, 'festival'])->name('festival');
        Route::get('/service-charge-and-tips', [PayrollReportController::class, 'pools'])->name('pools');
    });

    Route::middleware('can:payroll.loan.manage')->group(function (): void {
        Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
        Route::post('/loans/{loan}/installments/{installment}/skip', [LoanController::class, 'skip'])->name('loans.skip');
        Route::post('/loans/{loan}/installments/{installment}/waive', [LoanController::class, 'waive'])->name('loans.waive');
    });
    Route::middleware('can:payroll.loan.view')->group(function (): void {
        Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    });
});
