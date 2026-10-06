<?php

/*
|--------------------------------------------------------------------------
| Module 7 - Certification
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php.
*/

use App\Enums\UserRole;
use App\Http\Controllers\Admin\CertificationReviewController;
use App\Http\Controllers\CertificationController;
use Illuminate\Support\Facades\Route;

foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () {
        Route::resource('certifications', CertificationController::class);
    });
}

// Proof served from the private disk to its owner or an admin (policy checked in the controller).
Route::get('certifications/{certification}/document', [CertificationController::class, 'document'])->name('certifications.document');

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::get('certifications', [CertificationReviewController::class, 'index'])->name('certifications.index');
    Route::get('certifications/{certification}', [CertificationReviewController::class, 'show'])->name('certifications.show');
    Route::post('certifications/{certification}/approuver', [CertificationReviewController::class, 'approve'])->name('certifications.approve');
    Route::post('certifications/{certification}/refuser', [CertificationReviewController::class, 'reject'])->name('certifications.reject');
});
