<?php

use Illuminate\Support\Facades\Route;
use Modules\Rates\Http\Controllers\CancellationPolicyController;
use Modules\Rates\Http\Controllers\DepositPolicyController;
use Modules\Rates\Http\Controllers\PolicyController;
use Modules\Rates\Http\Controllers\PromotionController;
use Modules\Rates\Http\Controllers\RateGridController;
use Modules\Rates\Http\Controllers\RatePlanController;
use Modules\Rates\Http\Controllers\RateSheetController;
use Modules\Rates\Http\Controllers\SeasonController;

/*
|--------------------------------------------------------------------------
| Rates web routes (tenant subdomains)
|--------------------------------------------------------------------------
|
| URLs are prefixed with /rates; route names follow `rates.resource.action`. Screens work on
| the property chosen in the navbar; other properties' records never bind (404).
|
*/

Route::prefix('rates')->name('rates.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/grid', [RateGridController::class, 'index'])->middleware('can:rates.rate.view')->name('grid');
    Route::middleware('can:rates.rate.manage')->group(function (): void {
        Route::post('/rate-plans/{rate_plan}/overrides', [RateGridController::class, 'overrides'])->name('grid.overrides');
        Route::post('/rate-plans/{rate_plan}/restrictions', [RateGridController::class, 'restrictions'])->name('grid.restrictions');
        Route::put('/rate-plans/{rate_plan}/rates', [RateSheetController::class, 'update'])->name('rate-plans.rates.update');
    });
    Route::get('/rate-plans/{rate_plan}/rates', [RateSheetController::class, 'edit'])->middleware('can:rates.rate.view')->name('rate-plans.rates.edit');

    $write = ['create', 'store', 'edit', 'update', 'destroy'];

    Route::resource('rate-plans', RatePlanController::class)->only($write)->middleware('can:rates.rate-plan.manage');
    Route::get('/rate-plans', [RatePlanController::class, 'index'])->middleware('can:rates.rate-plan.view')->name('rate-plans.index');

    Route::middleware('can:rates.policy.manage')->group(function () use ($write): void {
        Route::resource('deposit-policies', DepositPolicyController::class)->only($write);
        Route::resource('cancellation-policies', CancellationPolicyController::class)->only($write);
    });
    Route::get('/policies', [PolicyController::class, 'index'])->middleware('can:rates.policy.view')->name('policies.index');

    Route::resource('promotions', PromotionController::class)->only($write)->middleware('can:rates.promotion.manage');
    Route::get('/promotions', [PromotionController::class, 'index'])->middleware('can:rates.promotion.view')->name('promotions.index');

    Route::resource('seasons', SeasonController::class)->only($write)->middleware('can:rates.season.manage');
    Route::get('/seasons', [SeasonController::class, 'index'])->middleware('can:rates.season.view')->name('seasons.index');
});
