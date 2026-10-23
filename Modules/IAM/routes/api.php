<?php

use Illuminate\Support\Facades\Route;
use Modules\IAM\Http\Controllers\Api\V1\MeController;

/*
|--------------------------------------------------------------------------
| IAM API routes (Step 8.1)
|--------------------------------------------------------------------------
|
| Loaded with the `api` and `tenant-api` middleware groups under /api (names prefixed `api.`).
| Versioned: /api/v1/iam/..., names `api.v1.iam.resource.action`.
|
*/

Route::prefix('v1/iam')->name('v1.iam.')->group(function (): void {
    Route::get('/me', MeController::class)->name('me.show');
});
