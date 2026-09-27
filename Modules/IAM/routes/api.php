<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IAM API routes
|--------------------------------------------------------------------------
|
| Loaded with the `api` middleware group under /api (names prefixed `api.`).
| Versioned: /api/v1/iam/..., names `api.v1.iam.resource.action`.
|
*/

Route::prefix('v1/iam')->name('v1.iam.')->group(function (): void {
    //
});
