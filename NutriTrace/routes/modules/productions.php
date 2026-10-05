<?php

/*
|--------------------------------------------------------------------------
| Module 2 - Production
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php.
*/

use App\Http\Controllers\ProductionController;
use Illuminate\Support\Facades\Route;

Route::prefix('producteur')->name('producteur.')->middleware('role:PRODUCTEUR')->group(function () {
    Route::resource('productions', ProductionController::class);
});
