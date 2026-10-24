<?php

use Illuminate\Support\Facades\Route;
use Modules\Website\Http\Controllers\Admin\SiteController;
use Modules\Website\Http\Controllers\Admin\UnitsController;
use Modules\Website\Http\Controllers\Public\PageController;

/*
|--------------------------------------------------------------------------
| Website web routes (Step 8.2a)
|--------------------------------------------------------------------------
|
| The public website at /resort/{site}/… (no sign-in; `website.site` finds the site and the language; names `website.*`), and its
| back office under /website (names `website.admin.*`). `/` itself goes through TenantHomePage (routes/tenant.php).
|
*/

Route::prefix('resort/{site}')->name('website.')->where(['site' => '[a-z0-9-]+'])->middleware(['website.site', 'throttle:website'])->group(function (): void {
    Route::get('/', [PageController::class, 'home'])->name('home');
    Route::get('/rooms', [PageController::class, 'rooms'])->name('rooms');
    Route::get('/rooms/{kind}/{id}', [PageController::class, 'room'])->whereIn('kind', ['room', 'cottage'])->whereNumber('id')->name('room');
    Route::get('/dining', [PageController::class, 'dining'])->name('dining');
    Route::get('/offers', [PageController::class, 'offers'])->name('offers');
    Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
    Route::get('/contact', [PageController::class, 'contact'])->name('contact');
    Route::get('/policies', [PageController::class, 'policies'])->name('policies');
});

Route::prefix('website')->name('website.admin.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/site', [SiteController::class, 'edit'])->middleware('can:website.content.view')->name('site.edit');
    Route::put('/site', [SiteController::class, 'update'])->middleware('can:website.content.manage')->name('site.update');
    Route::get('/units', [UnitsController::class, 'edit'])->middleware('can:website.content.view')->name('units.edit');
    Route::put('/units/{site}', [UnitsController::class, 'update'])->middleware('can:website.content.manage')->name('units.update');
});
