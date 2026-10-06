<?php

/*
|--------------------------------------------------------------------------
| Module 3 - Lot
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. Lots are created by a
| production or a transformation, never by hand, so there is no create/store.
*/

use App\Enums\UserRole;
use App\Http\Controllers\LotController;
use Illuminate\Support\Facades\Route;

foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () {
        Route::resource('lots', LotController::class)->only(['index', 'show', 'edit', 'update']);
    });
}

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    // Read-only for the admin.
    Route::resource('lots', LotController::class)->only(['index', 'show']);
});
