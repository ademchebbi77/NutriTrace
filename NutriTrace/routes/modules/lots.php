<?php

/*
|--------------------------------------------------------------------------
| Module 3 - Lot (with hand-overs to transformers and QR labels)
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. Lots are created by a
| production or a transformation, never by hand, so there is no create/store.
*/

use App\Enums\UserRole;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () {
        Route::resource('lots', LotController::class)->only(['index', 'show', 'edit', 'update']);

        // Printable QR label of a lot.
        Route::get('lots/{lot}/etiquette', [LabelController::class, 'show'])->name('labels.show');
    });
}

// Senders: producers and transformers.
foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () {
        Route::get('transferts', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('lots/{lot}/transferer', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('lots/{lot}/transferer', [TransferController::class, 'store'])->name('transfers.store');
    });
}

// Transformers receive the lots sent to them.
Route::prefix('transformateur')->name('transformateur.')->middleware('role:TRANSFORMATEUR')->group(function () {
    Route::get('receptions', [ReceptionController::class, 'index'])->name('receptions.index');
    Route::post('receptions/{transfer}/accepter', [ReceptionController::class, 'accept'])->name('receptions.accept');
    Route::post('receptions/{transfer}/refuser', [ReceptionController::class, 'reject'])->name('receptions.reject');
});

Route::prefix('distributeur')->name('distributeur.')->middleware('role:DISTRIBUTEUR')->group(function () {
    Route::get('etiquettes', [LabelController::class, 'index'])->name('labels.index');
});

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    // Read-only for the admin.
    Route::resource('lots', LotController::class)->only(['index', 'show']);
});
