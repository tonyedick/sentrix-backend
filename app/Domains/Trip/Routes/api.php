<?php

declare(strict_types=1);

use App\Domains\Trip\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization.team'])
    ->prefix('v1/organizations/{organization}')
    ->group(function (): void {
        Route::get('trips', [TripController::class, 'index'])->name('trips.index');
        Route::post('trips', [TripController::class, 'store'])->name('trips.store');
        Route::get('trips/{trip}', [TripController::class, 'show'])->name('trips.show');
        Route::patch('trips/{trip}', [TripController::class, 'update'])->name('trips.update');
        Route::post('trips/{trip}/complete', [TripController::class, 'complete'])->name('trips.complete');
        Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('trips.cancel');
    });
