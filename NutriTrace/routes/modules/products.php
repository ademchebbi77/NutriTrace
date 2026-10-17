<?php

/*
|--------------------------------------------------------------------------
| Module 1 - Product (and the categories managed by the admin)
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php.
*/

use App\Enums\UserRole;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Producers and transformers manage their own products.
foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () {
        Route::resource('produits', ProductController::class)
            ->names('products')
            ->parameters(['produits' => 'product']);

        // AI Product Risk Analysis
        Route::get('produits/{product}/analyse-risques', [ProductController::class, 'analyzeRisk'])
            ->name('products.risk-analysis');
    });
}

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    // Read-only for the admin.
    Route::resource('produits', ProductController::class)
        ->only(['index', 'show'])
        ->names('products')
        ->parameters(['produits' => 'product']);

    // AI Product Risk Analysis for admin
    Route::get('produits/{product}/analyse-risques', [ProductController::class, 'analyzeRisk'])
        ->name('products.risk-analysis');

    Route::resource('categories', CategoryController::class)->except(['create', 'show']);
});
