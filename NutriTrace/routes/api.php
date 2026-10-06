<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public read-only API
|--------------------------------------------------------------------------
| Routes API supprimées - plus de traçabilité publique nécessaire
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Routes API disponibles pour extension future
});
