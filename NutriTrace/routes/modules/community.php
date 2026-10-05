<?php

/*
|--------------------------------------------------------------------------
| Module 10 - Reviews and reports (consumer area and admin moderation)
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. Posting a review or a
| report happens on the public lot page: see the public routes in routes/web.php.
*/

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Consumer\AreaController;
use Illuminate\Support\Facades\Route;

Route::prefix('consommateur')->name('consommateur.')->middleware('role:CONSOMMATEUR')->group(function () {
    Route::get('/', [AreaController::class, 'dashboard'])->name('dashboard');
    Route::get('historique', [AreaController::class, 'history'])->name('history.index');
    Route::get('favoris', [AreaController::class, 'favorites'])->name('favorites.index');
    Route::get('avis', [AreaController::class, 'reviews'])->name('reviews.index');
    Route::get('signalements', [AreaController::class, 'reports'])->name('reports.index');
});

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::resource('signalements', ReportController::class)
        ->only(['index', 'show', 'update'])
        ->names('reports')
        ->parameters(['signalements' => 'report']);

    Route::get('journal', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
