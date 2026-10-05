<?php

/*
|--------------------------------------------------------------------------
| Module 5 - Transport
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. Transports are created
| when a lot is sent (module Lot); here they are followed and corrected.
*/

use App\Enums\UserRole;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Route;

foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () {
        Route::resource('transports', TransportController::class)->only(['index', 'show', 'update']);
    });
}

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::resource('transports', TransportController::class)->only(['index', 'show']);
});
