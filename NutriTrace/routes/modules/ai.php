<?php

/*
|--------------------------------------------------------------------------
| Module 5 - Transport - Traçabilité: local AI
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. The public route of
| the assistant is in web.php, next to the traceability page.
*/

use App\Enums\UserRole;
use App\Http\Controllers\TransportAiController;
use Illuminate\Support\Facades\Route;

foreach ([UserRole::ADMIN, UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware(['role:'.$role->value, 'throttle:10,1'])->group(function () {
        Route::post('transports/{transport}/assistant', [TransportAiController::class, 'ask'])->name('transports.assistant');
        Route::post('transports/{transport}/analyse', [TransportAiController::class, 'analyze'])->name('transports.analyze');
    });
}
