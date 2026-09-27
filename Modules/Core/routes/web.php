<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core web routes
|--------------------------------------------------------------------------
|
| URLs are prefixed with /core and route names follow
| `core.resource.action`. Every route must be protected by
| authentication and permission middleware (see docs/MODULE_GUIDE.md).
|
*/

Route::prefix('core')->name('core.')->group(function (): void {
    //
});
