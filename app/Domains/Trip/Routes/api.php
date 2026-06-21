<?php

declare(strict_types=1);

use App\Domains\Trip\Http\Controllers\ConsumerRouteController;
use App\Domains\Trip\Http\Controllers\ConsumerTripController;
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

/*
 | Consumer trips (user-scoped, ADR-0001). The monitored user is the authenticated
 | user; the serving org is resolved server-side. Bearer-token (mobile) auth.
 */
Route::middleware('auth:sanctum')->prefix('v1/me')->group(function (): void {
    Route::get('trips', [ConsumerTripController::class, 'index'])->name('me.trips.index');
    Route::post('trips', [ConsumerTripController::class, 'store'])->name('me.trips.store');
    Route::get('trips/{trip}', [ConsumerTripController::class, 'show'])->name('me.trips.show');
    Route::post('trips/{trip}/complete', [ConsumerTripController::class, 'complete'])->name('me.trips.complete');
    Route::post('trips/{trip}/cancel', [ConsumerTripController::class, 'cancel'])->name('me.trips.cancel');
    Route::post('trips/{trip}/locations', [ConsumerTripController::class, 'storeLocations'])->name('me.trips.locations.store');

    // Route planning (Safest / Fastest) — stateless.
    Route::post('routes/plan', [ConsumerRouteController::class, 'plan'])->name('me.routes.plan');
});
