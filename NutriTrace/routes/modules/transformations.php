<?php

/*
|--------------------------------------------------------------------------
| Module 4 - Transformation
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php.
*/

use App\Http\Controllers\TransformationController;
use Illuminate\Support\Facades\Route;

Route::prefix('transformateur')->name('transformateur.')->middleware('role:TRANSFORMATEUR')->group(function () {
    Route::resource('transformations', TransformationController::class)->only(['index', 'create', 'store', 'show']);
});

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::resource('transformations', TransformationController::class)->only(['index', 'show']);
});
