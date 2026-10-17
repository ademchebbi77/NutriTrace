<?php

/*
|--------------------------------------------------------------------------
| Local AI of the modules Transport - Traçabilité, Distribution - Certification
| and Transformation - Lots
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. The public route of
| the lot assistant is in web.php, next to the traceability page. Generation
| is costly: the routes that call the LLM are tightly rate limited.
*/

use App\Enums\UserRole;
use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\CertificationAiController;
use App\Http\Controllers\DistributionAiController;
use App\Http\Controllers\Distributor\AssistantController;
use App\Http\Controllers\TransformationAiController;
use App\Http\Controllers\TransportAiController;
use Illuminate\Support\Facades\Route;

// Transport - Traçabilité
foreach ([UserRole::ADMIN, UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware(['role:'.$role->value, 'throttle:10,1'])->group(function () {
        Route::post('transports/{transport}/assistant', [TransportAiController::class, 'ask'])->name('transports.assistant');
        Route::post('transports/{transport}/analyse', [TransportAiController::class, 'analyze'])->name('transports.analyze');
    });
}

// Distribution - Certification
Route::prefix('distributeur')->name('distributeur.')->middleware('role:DISTRIBUTEUR')->group(function () {
    Route::get('assistant', [AssistantController::class, 'show'])->name('assistant.show');
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('assistant', [AssistantController::class, 'ask'])->name('assistant.ask');
        Route::post('distributions/{distribution}/verifier', [DistributionAiController::class, 'check'])->name('distributions.check');
    });
});

// Transformation - Lots
Route::prefix('transformateur')->name('transformateur.')->middleware(['role:TRANSFORMATEUR', 'throttle:10,1'])->group(function () {
    Route::post('transformations/verifier', [TransformationAiController::class, 'check'])->name('transformations.check');
});

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::post('certifications/{certification}/expliquer', [CertificationAiController::class, 'explain'])
        ->middleware('throttle:10,1')
        ->name('certifications.explain');
    Route::post('transformations/{transformation}/expliquer', [TransformationAiController::class, 'explain'])
        ->middleware('throttle:10,1')
        ->name('transformations.explain');

    // Training and monitoring of the local models.
    Route::get('ia', [AiController::class, 'index'])->name('ai.index');
    Route::get('ia/statut', [AiController::class, 'status'])->name('ai.status');
    Route::post('ia/entrainer', [AiController::class, 'train'])->name('ai.train');
    Route::post('ia/annuler', [AiController::class, 'cancel'])->name('ai.cancel');
});
