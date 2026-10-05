<?php

/*
|--------------------------------------------------------------------------
| Module 6 - Distribution (reception, shelving and sales)
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. A distribution is created
| when a lot is sent to a distributor (module Lot).
*/

use App\Http\Controllers\DistributionController;
use App\Http\Controllers\ReceptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('distributeur')->name('distributeur.')->middleware('role:DISTRIBUTEUR')->group(function () {
    Route::get('receptions', [ReceptionController::class, 'index'])->name('receptions.index');

    Route::resource('distributions', DistributionController::class)->only(['index', 'show']);
    Route::post('distributions/{distribution}/recevoir', [DistributionController::class, 'receive'])->name('distributions.receive');
    Route::post('distributions/{distribution}/refuser', [DistributionController::class, 'reject'])->name('distributions.reject');
    Route::post('distributions/{distribution}/mise-en-rayon', [DistributionController::class, 'markInStore'])->name('distributions.in-store');

    Route::get('ventes', [DistributionController::class, 'sales'])->name('sales.index');
    Route::post('distributions/{distribution}/ventes', [DistributionController::class, 'sell'])->name('sales.store');
});

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::resource('distributions', DistributionController::class)->only(['index', 'show']);
});
