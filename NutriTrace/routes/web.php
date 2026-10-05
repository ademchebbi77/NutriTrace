<?php

use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\Producer;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\CatalogController;
use App\Http\Controllers\PublicSite\CompareController;
use App\Http\Controllers\PublicSite\FeedbackController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\TraceController;
use App\Http\Controllers\RoleDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site (no login, rate limited)
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:public')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/comment-ca-marche', [HomeController::class, 'howItWorks'])->name('how-it-works');

    Route::get('/produits', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/produits/{product}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::get('/recherche', [CatalogController::class, 'lookup'])->name('lookup');
    Route::get('/comparer', CompareController::class)->name('compare');

    // Public traceability page of a lot, target of the QR code.
    Route::get('/trace/{token}', [TraceController::class, 'show'])->name('trace.show');
    Route::get('/trace/{token}/certifications/{certification}/preuve', [TraceController::class, 'proof'])->name('trace.proof');
});

// Actions of a signed-in visitor on the public pages.
Route::middleware(['auth', 'verified', 'active', 'throttle:20,1'])->group(function () {
    Route::post('/trace/{token}/avis', [FeedbackController::class, 'review'])->name('trace.review');
    Route::delete('/avis/{review}', [FeedbackController::class, 'destroyReview'])->name('reviews.destroy');
    Route::post('/trace/{token}/signalement', [FeedbackController::class, 'report'])->name('trace.report');
    Route::post('/produits/{product}/favori', [FeedbackController::class, 'toggleFavorite'])->name('favorites.toggle');
});

/*
|--------------------------------------------------------------------------
| Account (any authenticated user, even pending or deactivated)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/compte/statut', AccountStatusController::class)->name('account.status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Back office (verified email + approved, active account)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:PRODUCTEUR,TRANSFORMATEUR,DISTRIBUTEUR')->group(function () {
        Route::get('/organisation', [OrganizationController::class, 'edit'])->name('organization.edit');
        Route::patch('/organisation', [OrganizationController::class, 'update'])->name('organization.update');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/comptes-en-attente', [Admin\ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/comptes-en-attente/{user}/approuver', [Admin\ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('/comptes-en-attente/{user}/rejeter', [Admin\ApprovalController::class, 'reject'])->name('approvals.reject');

        Route::get('/utilisateurs', [Admin\UserController::class, 'index'])->name('users.index');
        Route::patch('/utilisateurs/{user}/activation', [Admin\UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::patch('/organisations/{organization}/verification', [Admin\UserController::class, 'toggleVerified'])->name('users.toggle-verified');
    });

    Route::prefix('producteur')->name('producteur.')->middleware('role:PRODUCTEUR')->group(function () {
        Route::get('/', Producer\DashboardController::class)->name('dashboard');
    });

    Route::prefix('transformateur')->name('transformateur.')->middleware('role:TRANSFORMATEUR')->group(function () {
        Route::get('/', RoleDashboardController::class)->name('dashboard');
    });

    Route::prefix('distributeur')->name('distributeur.')->middleware('role:DISTRIBUTEUR')->group(function () {
        Route::get('/', RoleDashboardController::class)->name('dashboard');
    });

    // Business modules: one routes file per module (the consumer area is in community.php).
    require __DIR__.'/modules/products.php';
    require __DIR__.'/modules/productions.php';
    require __DIR__.'/modules/lots.php';
    require __DIR__.'/modules/transformations.php';
    require __DIR__.'/modules/transports.php';
    require __DIR__.'/modules/distributions.php';
    require __DIR__.'/modules/certifications.php';
    require __DIR__.'/modules/impacts.php';
    require __DIR__.'/modules/community.php';
});

require __DIR__.'/auth.php';
