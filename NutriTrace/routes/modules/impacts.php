<?php

/*
|--------------------------------------------------------------------------
| Module 8 - Environmental impact (and the scoring settings of the admin)
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php.
*/

use App\Enums\UserRole;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\ImpactController;
use Illuminate\Support\Facades\Route;

foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () {
        Route::get('impacts', [ImpactController::class, 'index'])->name('impacts.index');
        Route::get('impacts/{lot}', [ImpactController::class, 'edit'])->name('impacts.edit');
        Route::put('impacts/{lot}', [ImpactController::class, 'update'])->name('impacts.update');
    });
}

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::get('parametres', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('parametres', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('parametres', [SettingsController::class, 'reset'])->name('settings.reset');
});
