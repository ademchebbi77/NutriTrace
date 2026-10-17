<?php

/*
|--------------------------------------------------------------------------
| Module 5 - Transport
|--------------------------------------------------------------------------
| Loaded inside the back office group of routes/web.php. Creating a transport
| sends a lot (also possible from the Lot module); "destroy" cancels the
| shipment while it is on the road, the record stays for the journal.
*/

use App\Enums\UserRole;
use App\Http\Controllers\TransportController;
use Illuminate\Support\Facades\Route;

foreach ([UserRole::PRODUCTEUR, UserRole::TRANSFORMATEUR, UserRole::DISTRIBUTEUR] as $role) {
    Route::prefix($role->prefix())->name($role->prefix().'.')->middleware('role:'.$role->value)->group(function () use ($role) {
        // Distributors receive lots but never send them.
        Route::resource('transports', TransportController::class)
            ->except($role === UserRole::DISTRIBUTEUR ? ['create', 'store'] : []);
    });
}

Route::prefix('admin')->name('admin.')->middleware('role:ADMIN')->group(function () {
    Route::resource('transports', TransportController::class)->only(['index', 'show']);
});
