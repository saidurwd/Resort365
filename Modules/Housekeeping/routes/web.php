<?php

use Illuminate\Support\Facades\Route;
use Modules\Housekeeping\Http\Controllers\BlockController;
use Modules\Housekeeping\Http\Controllers\BoardController;
use Modules\Housekeeping\Http\Controllers\LostFoundController;
use Modules\Housekeeping\Http\Controllers\ScheduleController;
use Modules\Housekeeping\Http\Controllers\TaskController;
use Modules\Housekeeping\Http\Controllers\WorkOrderController;

/*
|--------------------------------------------------------------------------
| Housekeeping web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /housekeeping; route names follow `housekeeping.resource.action`.
| Screens work on the property chosen in the navbar, on its business date.
|
*/

Route::prefix('housekeeping')->name('housekeeping.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/board', [BoardController::class, 'index'])->middleware('can:housekeeping.board.view')->name('board');
    Route::post('/board/status', [BoardController::class, 'status'])->middleware('can:housekeeping.room.update')->name('board.status');

    Route::get('/tasks', [TaskController::class, 'index'])->middleware('can:housekeeping.task.manage')->name('tasks.index');
    Route::post('/tasks/generate', [TaskController::class, 'generate'])->middleware('can:housekeeping.task.manage')->name('tasks.generate');
    Route::post('/tasks/assign', [TaskController::class, 'assign'])->middleware('can:housekeeping.task.manage')->name('tasks.assign');
    Route::get('/my-tasks', [TaskController::class, 'mine'])->middleware('can:housekeeping.task.perform')->name('tasks.mine');
    Route::post('/tasks/{task}/{step}', [TaskController::class, 'step'])->whereNumber('task')->whereIn('step', ['start', 'finish', 'pass', 'fail', 'skip'])->name('tasks.step');

    Route::middleware('can:housekeeping.block.manage')->group(function (): void {
        Route::get('/blocks', [BlockController::class, 'index'])->name('blocks.index');
        Route::post('/blocks', [BlockController::class, 'store'])->name('blocks.store');
        Route::post('/blocks/{block}/end', [BlockController::class, 'end'])->name('blocks.end');
    });

    Route::middleware('can:housekeeping.work-order.view')->group(function (): void {
        Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('/work-orders/data', [WorkOrderController::class, 'data'])->name('work-orders.data');
    });
    Route::middleware('can:housekeeping.work-order.create')->group(function (): void {
        Route::get('/work-orders/new', [WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    });
    Route::get('/work-orders/{order}', [WorkOrderController::class, 'show'])->whereNumber('order')->name('work-orders.show');
    Route::put('/work-orders/{order}', [WorkOrderController::class, 'update'])->whereNumber('order')->name('work-orders.update');

    Route::middleware('can:housekeeping.schedule.manage')->group(function (): void {
        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::get('/schedules/new', [ScheduleController::class, 'create'])->name('schedules.create');
        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::get('/schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->name('schedules.edit');
        Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
        Route::post('/schedules/run', [ScheduleController::class, 'run'])->name('schedules.run');
    });

    Route::middleware('can:housekeeping.lost-found.view')->group(function (): void {
        Route::get('/lost-found', [LostFoundController::class, 'index'])->name('lost-found.index');
        Route::get('/lost-found/data', [LostFoundController::class, 'data'])->name('lost-found.data');
    });
    Route::middleware('can:housekeeping.lost-found.manage')->group(function (): void {
        Route::post('/lost-found', [LostFoundController::class, 'store'])->name('lost-found.store');
        Route::post('/lost-found/{item}/close', [LostFoundController::class, 'close'])->name('lost-found.close');
    });
});
