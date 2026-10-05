<?php

use App\Http\Controllers\Api\TraceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public read-only API (prefix /api, rate limited by the "api" limiter)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('trace/{token}', [TraceController::class, 'show'])->name('trace.show');
});
